..  include:: /Includes.rst.txt
..  _developer-api-discounts:

==============
Discounts API
==============

The Discounts API enables integrators to implement price reductions for vouchers, promotional codes,
customer-group rebates, loyalty point redemptions, or any business rule that lowers what the customer
pays. A discount provider is a service that receives a checkout context, decides whether a discount
applies, and optionally books the redemption after payment. Because discounting is inherently
shop-specific, **the extension ships with only the voucher feature** (a built-in discount provider);
integrators add custom discounts by implementing the interface.

**Location:** :php:`GoldeneZeiten\Products\Discount\DiscountProviderInterface`

The Two Phases: Quote and Apply
================================

The Discount API is built on a **separation of concerns**: discounts are computed in a read-only
**quote phase** and later booked in a transactional **apply phase**. This separation is crucial:

**Quote Phase — Read-Only Computation**
    Called whenever a total needs to be displayed (during checkout review, on every keystroke,
    in an email preview). Must never side-effect: no voucher is marked used, no redemption row
    is written. The method may be called any number of times without affecting state.

    Returning from :php:`quote()` means "if the customer confirms this order, this is the
    discount I would apply" — not "I am now applying it."

**Apply Phase — Transactional Booking**
    Called exactly once per order, inside the order transaction. This is where vouchers are marked
    as redeemed, loyalty points are deducted, redemption records are written. If this method throws,
    the entire order placement is rolled back, protecting data integrity.

This separation lets a free-shipping voucher display the final total for review, then later
(after the customer confirms and payment succeeds) mark the voucher as used.

Interface Contract
==================

..  code-block:: php

    interface DiscountProviderInterface
    {
        public function getIdentifier(): string;
        public function getPriority(): int;
        public function quote(DiscountContext $context): array;
        public function apply(Order $order, DiscountContext $context): void;
    }

**Methods:**

:php:`getIdentifier(): string`
    A unique identifier for this discount type (e.g., :code:`'campaign_summer_2024'`,
    :code:`'loyalty_points'`, :code:`'vip_rebate'`). Used in adjustment metadata for
    reconciliation and to identify which provider created which adjustment.

:php:`getPriority(): int`
    Higher values run first. Providers with the same priority keep their registration order.
    Use :code:`0` for default priority. **Ordering matters** because a later discount may
    offset an earlier one (see `Offsetting an Earlier Adjustment`_ below).

    Example: A loyalty-point discount runs at priority :code:`10`, then a free-shipping
    voucher runs at priority :code:`5`, and it offsets the shipping adjustment that was
    already added by the shipping provider at priority :code:`0`.

:php:`quote(DiscountContext $context): CheckoutAdjustment[]`
    **Quote phase (read-only).** Compute the discount for this context and return it as zero
    or more :php:`CheckoutAdjustment` objects. An empty array means the discount does not apply.
    Never mark a voucher used or write any data here.

    The :php:`$context` includes applied codes (e.g., voucher codes the customer entered), so
    your provider can check whether this discount is triggered. A concrete example: return a
    discount only if the context's applied codes contain :code:`'LOYALTY_GOLD'`.

:php:`apply(Order $order, DiscountContext $context): void`
    **Apply phase (transactional).** Book the discount against the placed order. This is where
    you mark vouchers as redeemed, increment redemption counters, or deduct loyalty points.
    Runs inside the order transaction; throwing rolls back the entire order. The :php:`$context`
    is the same one passed to :php:`quote()`, so you can check the applied codes again to know
    which voucher to redeem.

DiscountContext
===============

An immutable value object passed to both :php:`quote()` and :php:`apply()`. It carries:

:php:`getGoodsTotal(): Money`
    The basket's gross total before any adjustments (shipping, discounts, fees, etc.).
    Discounts use this to apply percentage-based or minimum-threshold rules.

:php:`getFrontendUserUid(): int`
    The UID of the logged-in frontend user (0 if guest). Use this to offer customer-specific
    discounts (e.g., VIP rebates, returning-customer incentives).

:php:`getAppliedCodes(): string[]`
    An array of codes the customer entered at checkout (e.g., :code:`['SUMMER20', 'LOYALTY_GOLD']`).
    Your provider checks whether the codes it recognizes are in this list.

:php:`getAccumulatedAdjustments(): AdjustmentCollection`
    The adjustments contributed by providers that ran **before** you. This is how you offset an
    earlier adjustment without knowing where it came from. A free-shipping discount finds the
    shipping adjustment in this collection and negates it (see `Offsetting an Earlier Adjustment`_
    below).

CheckoutAdjustment
===================

The return type of :php:`quote()`. Construct it with:

..  code-block:: php

    new CheckoutAdjustment(
        AdjustmentType $type,        // Always AdjustmentType::DISCOUNT for discounts
        string $providerIdentifier,  // Your getIdentifier() return value
        string $label,               // Human-readable description for order display
        Money $amount,               // Signed: negative reduces the total (e.g., -500 for -5.00 EUR)
        float $taxRate = 0.0,        // Tax rate (0 for non-taxable discounts)
        array $metadata = []         // Provider-private detail (voucher code, points spent, ...)
    )

For discounts:
- Always use :php:`AdjustmentType::DISCOUNT` (see :ref:`Checkout Adjustments API
  <developer-api-checkout-adjustments>` for other types).
- The :php:`$amount` is **signed negative** (e.g., :code:`Money::fromCents(-500)` for -5.00 EUR).
- The :php:`$label` is denormalized; set it at creation time so orders render correctly even if
  your extension is later uninstalled.
- The :php:`$metadata` is private to your provider. Store whatever helps you reconcile or debug
  (e.g., :code:`['code' => 'SUMMER20', 'points_spent' => '1500']`).

Offsetting an Earlier Adjustment
=================================

A discount can offset an adjustment a previous provider added, without either provider knowing
about the other. This is the key to decoupling and the reason for the priority system.

**Concrete example: Free Shipping**

1. The shipping provider (priority :code:`0`) contributes a :php:`CheckoutAdjustment(SHIPPING, ...)`
   with amount :code:`Money::fromCents(595)` (5.95 EUR).

2. Your free-shipping discount provider (priority :code:`5`) runs later and **sees the adjustment
   in the context**:

   ..  code-block:: php

       $shippingAdjustments = $context->getAccumulatedAdjustments()->byType(AdjustmentType::SHIPPING);
       foreach ($shippingAdjustments as $adjustment) {
           if ($adjustment->getProviderIdentifier() === CoreAdjustmentProvider::SHIPPING) {
               // Found the carrier's cost; offset it entirely
               $amount = Money::fromCents(-$adjustment->getAmount()->getCents());
               return [new CheckoutAdjustment(AdjustmentType::DISCOUNT, 'my-freeship', 'Free Shipping', $amount)];
           }
       }

3. The total of both adjustments (:code:`+595 -595`) is zero. The shipping is free.

Neither provider reached into the other's code or domain. The discount provider does not know
where the shipping adjustment came from; the shipping provider never heard of the discount.
This works because **adjustments are a ledger of causality**, not a side-effect system.

Registration
============

A class implementing :php:`DiscountProviderInterface` is automatically registered — no manual
entry in :file:`Configuration/Services.yaml` is required. The interface itself carries the
:php:`#[AutoconfigureTag('products.discount_provider')]` attribute, so Symfony's autowiring
discovers and collects all implementations.

Discounts Are Optional
======================

Unlike shipping or payment (which are mandatory for any order), **discounts are on-top
functionality**. A shop with no discount providers still checks out successfully:

-   The :php:`DiscountRegistry` is instantiated with zero providers if none are registered.
-   Calling :php:`collect(DiscountContext)` with no providers returns an empty array.
-   The order total is unaffected.

The extension ships the :php:`VoucherDiscountProvider` (which implements this interface) so shops
have vouchers out of the box, but it is itself just one more discount provider. Uninstalling the
voucher feature (if it were a separate extension) would simply remove one provider from the registry.

Customer Input and Checkout State
==================================

While the discount providers compute and apply reductions, customer input (the codes they enter,
options they select) is stored separately from the basket DTO. The **checkout state** is a
feature-specific slice of session storage, managed by :php:`CheckoutStateStore`.

**Why separate input from the basket?**
The basket DTO carries only items and their quantities — it is feature-agnostic. Voucher codes,
loyalty-program selections, or other discount-specific input belong in their own state container.
This keeps the basket clean and lets discount features evolve independently without touching
core basket code.

**The Pattern: VoucherCheckoutState**

The voucher feature demonstrates this:

-   **Customer enters codes:** Via :php:`VoucherController` (actions :php:`apply()` and :php:`remove()`),
    the frontend submits a voucher code request.

-   **Codes are stored:** :php:`VoucherCheckoutState` persists them in the session under a provider-scoped
    key (using :php:`CoreAdjustmentProvider::VOUCHER` as the identifier).

-   **State is checked during quote:** When :php:`VoucherDiscountProvider::quote()` runs, it calls
    :php:`VoucherCheckoutState::getCodes()` to fetch the applied codes and decide whether a
    discount applies.

-   **UI renders the state:** A template partial uses :php:`VoucherSummaryViewHelper` to fetch the
    current codes and display them to the customer, without needing the basket DTO to carry them.

**Implementing this for a custom discount feature**

If you build a custom discount feature (e.g., a loyalty-points selector), follow the same pattern:

1.  Create a checkout state holder similar to :php:`VoucherCheckoutState`:

    -   Accept :php:`CheckoutStateStore` in the constructor.
    -   Implement methods like :php:`getCodes()`, :php:`addCode()`, :php:`removeCode()`.
    -   Store your state under your provider's identifier key.

2.  Create a controller to accept customer input (the frontend form).

3.  In your discount provider's :php:`quote()` method, fetch the state via your checkout-state class.

4.  In your template, fetch the state via a ViewHelper or direct service injection to display
    the current selection.

The basket remains decoupled from your feature; the checkout store handles session persistence.

Complete Example: A Flat 5 EUR Discount
=======================================

Here is a minimal discount provider that grants 5.00 EUR off when the code :code:`'FLAT5'` is
applied:

..  code-block:: php

    <?php
    declare(strict_types=1);

    namespace Acme\MyDiscount;

    use GoldeneZeiten\Products\Discount\DiscountProviderInterface;
    use GoldeneZeiten\Products\Domain\Dto\Discount\DiscountContext;
    use GoldeneZeiten\Products\Domain\Enum\AdjustmentType;
    use GoldeneZeiten\Products\Domain\Model\Order;
    use GoldeneZeiten\Products\Domain\ValueObject\CheckoutAdjustment;
    use GoldeneZeiten\Products\Domain\ValueObject\Money;

    final class FlatDiscountProvider implements DiscountProviderInterface
    {
        public function getIdentifier(): string
        {
            return 'acme-flat5';
        }

        public function getPriority(): int
        {
            return 0;
        }

        public function quote(DiscountContext $context): array
        {
            // Check whether the customer entered the code
            if (!in_array('FLAT5', $context->getAppliedCodes(), true)) {
                return [];
            }

            // Return a discount of 5.00 EUR (500 cents, negative)
            return [
                new CheckoutAdjustment(
                    AdjustmentType::DISCOUNT,
                    $this->getIdentifier(),
                    'Flat 5 EUR Off',
                    Money::fromCents(-500)
                ),
            ];
        }

        public function apply(Order $order, DiscountContext $context): void
        {
            // In this example, we have nothing to book (no voucher to mark used).
            // A real example might log the redemption or decrement a counter.
        }
    }

Register it in :file:`Configuration/Services.yaml`:

..  code-block:: yaml

    services:
      _defaults:
        autowire: true
        autoconfigure: true
        public: false

      Acme\MyDiscount\FlatDiscountProvider: ~

That is all. The :php:`#[AutoconfigureTag]` attribute on the interface does the rest.

**Testing the discount**

In a functional test, use the :php:`DiscountRegistry` to verify:

..  code-block:: php

    $registry = $this->get(DiscountRegistry::class);
    $factory = $this->get(DiscountContextFactory::class);

    $basket = new BasketViewModel([...], $gross, $gross, $tax, 'EUR');
    $context = $factory->createFromBasket($basket, $frontendUserUid, ['FLAT5'], new AdjustmentCollection());

    $adjustments = $registry->collect($context);

    // Verify the discount is in the adjustments
    $flat5 = array_filter(
        $adjustments,
        static fn(CheckoutAdjustment $adj): bool => $adj->getProviderIdentifier() === 'acme-flat5'
    );
    $this->assertCount(1, $flat5);
    $this->assertSame(-500, array_values($flat5)[0]->getAmount()->getCents());

See Also
========

-   :ref:`Checkout Adjustments API <developer-api-checkout-adjustments>` — How adjustments
    affect order totals and tax handling.
-   :php:`AdjustmentCollection` — Methods to filter, sum, and inspect adjustments by type.
-   :php:`Money` — The value object for money amounts (always in cents for precision).
