..  include:: /Includes.rst.txt
..  _developer-api-checkout-adjustments:

=======================
Checkout Adjustments API
=======================

A :php:`CheckoutAdjustment` is the **only way** a feature may change what the customer pays. When
shipping, vouchers, credit points, deposits, or handling fees need to affect the order total, they
do so by contributing a signed money adjustment — never by writing directly into the order. This
isolation lets features live in separate extensions without knowing about each other; a voucher
does not need to know about shipping, and a loyalty system does not need to know about deposits.

**Location:** :php:`GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment`

The Adjustment Type System
==========================

Every adjustment has a type, defined by the :php:`AdjustmentType` enum:

-   **SHIPPING** — The cost of delivering the order to the customer. Typically the only taxable adjustment.
-   **HANDLING** — A handling or processing fee that applies after the order is paid. Not taxable.
-   **DISCOUNT** — A price reduction from a voucher or promotion code. Not taxable; always negative.
-   **LOYALTY** — A price reduction from spending loyalty or credit points. Not taxable; always negative.
-   **PAYMENT_FEE** — A fee charged by the payment method (e.g., credit card surcharge). Not taxable.
-   **DEPOSIT** — A returnable deposit (e.g., for a bottle or container). Not taxable.

Two types, :php:`DISCOUNT` and :php:`LOYALTY`, are **reducing types**. Their signed amounts
are negative (they reduce the total), and they are reported together as the order's single
``discount_total`` — a positive magnitude that represents all reductions the customer received.

Ordering Matters
================

Adjustments are held in the order they were contributed — the order the providers ran during order
creation. This is essential for decoupling:

**Concrete example:** A free-shipping voucher does not reach into the shipping adjustment or even
know the shipping cost. Instead:

1. The shipping provider contributes a :php:`CheckoutAdjustment(SHIPPING, ...)` with the calculated cost.
2. The voucher provider, running later, sees the adjustment collection already contains the shipping cost.
3. The voucher provider emits a :php:`CheckoutAdjustment(DISCOUNT, ...)` with a negative amount equal to the shipping cost.
4. The total of both adjustments is zero — the shipping is free.

Neither provider needed to know about the other. The adjustment collection is not just a list; it is a
**ledger of causality** that makes independent features composable.

How Adjustments Become Order Totals
===================================

An adjustment is a signed :php:`Money` amount. When the order is created, the :php:`OrderFactory` folds
the adjustment collection into the order's totals:

-   The **signed sum** of all adjustments moves the gross total (e.g., :code:`+15.00` for shipping, :code:`-5.00` for a voucher).
-   Adjustments carrying a **tax rate** additionally split into a net and tax share; the rest move only the gross total.
-   The **per-type totals** (``shipping_total``, ``handling_fee_total``, ``discount_total``, ``deposit_total``) are
    **derived values** written to the order for invoices and the backend. The adjustment collection is the source of truth.

Tax Handling
============

Only adjustments with a ``taxRate > 0`` (currently, shipping) split into a net and tax share:

:php:`isTaxable(): bool`
    Returns :code:`true` if the adjustment carries a tax rate.

:php:`getNetAmount(): Money`
    For taxable adjustments, returns the net (pre-tax) portion. For others, returns zero.

:php:`getTaxAmount(): Money`
    For taxable adjustments, returns the tax share. For others, returns zero.

For a taxable adjustment with amount :code:`119.00` (gross) at 19% tax:

-   Net: :code:`100.00`
-   Tax: :code:`19.00`

The adjustment collection aggregates these splits automatically.

Denormalization: The Label
===========================

Every adjustment carries a **label** — a human-readable description (e.g., :code:`'DHL International'` for
shipping, :code:`'Loyalty Points'` for credit redemption). The label is **intentionally denormalized**.

Why? An order must still render correctly and be invoiceable after the extension that produced the
adjustment is uninstalled. Without the denormalized label, the backend would show a blank or error when
rendering an order placed weeks ago with an addon that is no longer active.

The label is the responsibility of the provider that created the adjustment; backends must never try
to re-derive or look it up. If you are writing an adjustment provider, set the label at the moment
of adjustment creation, and do not rely on any external service to supply it later.

The :php:`AdjustmentCollection` API
===================================

The collection is **immutable**. All methods return new collections or non-mutating results:

:php:`all(): CheckoutAdjustment[]`
    Returns all adjustments in the order they were contributed.

:php:`byType(AdjustmentType $type): CheckoutAdjustment[]`
    Returns only adjustments of the given type, in contribution order.

:php:`with(CheckoutAdjustment $adjustment): self`
    Returns a new collection with the adjustment appended. The original is unchanged.

:php:`getTotal(): Money`
    Signed sum of all adjustments — what they add to (or subtract from) the basket's gross total.

:php:`getTotalByType(AdjustmentType $type): Money`
    Signed sum of adjustments of a single type.

:php:`getNetTotal(): Money`
    Sum of the net portions of all taxable adjustments.

:php:`getTaxTotal(): Money`
    Sum of the tax shares of all taxable adjustments.

:php:`getDiscountTotal(): Money`
    The magnitude (positive amount) of all reducing adjustments (DISCOUNT and LOYALTY types).
    Used to fill the order's ``discount_total`` field.

Atomicity and Order Placement
==============================

Order placement is now wrapped in a database transaction via :php:`OrderCreationService::create()`.
All of the following either succeed together or fail together:

-   Stock decrements (order items)
-   Voucher redemptions
-   Credit point bookings and transactions
-   Order persistence

If any step fails (e.g., insufficient stock, invalid voucher, or insufficient credit points), the
transaction rolls back — no order is created, and no side effects land in the database. This
guarantees that an order and its adjustments are always consistent.

Provider Identifiers
====================

Adjustments include a **provider identifier** to help backends and integrations track which extension
or service contributed each adjustment. Core adjustment providers use these identifiers:

-   :code:`'core.shipping'` — Shipping cost
-   :code:`'core.handling'` — Handling fee
-   :code:`'core.voucher'` — Voucher discount
-   :code:`'core.credit_points'` — Credit points redemption
-   :code:`'core.deposit'` — Deposit charges

Custom providers (e.g., external integrations) should use a namespaced identifier like
:code:`'my_vendor.my_feature'` to avoid collisions.

Example: Building and Using Adjustments
========================================

This example constructs a collection of adjustments for a typical order:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Service;

    use GoldeneZeiten\Products\Core\Domain\Enum\AdjustmentType;
    use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
    use GoldeneZeiten\Products\Core\Domain\ValueObject\CheckoutAdjustment;
    use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;

    final class CheckoutAdjustmentExample
    {
        public function exampleOrderAdjustments(): void
        {
            // Base item cost: 100.00 (gross)
            $basketGross = Money::fromDecimalString('100.00');

            // Shipping: 15.00 at 19% tax
            $shippingAdjustment = new CheckoutAdjustment(
                AdjustmentType::SHIPPING,
                'core.shipping',
                'Standard Shipping (DHL)',
                Money::fromDecimalString('15.00'),
                0.19
            );

            // Voucher discount: -10.00 (negative to reduce total)
            $voucherAdjustment = new CheckoutAdjustment(
                AdjustmentType::DISCOUNT,
                'core.voucher',
                'Voucher: SAVE10',
                Money::fromDecimalString('-10.00')
            );

            // Build the collection
            $adjustments = new AdjustmentCollection(
                $shippingAdjustment,
                $voucherAdjustment
            );

            // Inspect the result
            echo 'All adjustments: ' . count($adjustments->all()) . "\n";
            // Output: All adjustments: 2

            echo 'Shipping adjustments: ' . count($adjustments->byType(AdjustmentType::SHIPPING)) . "\n";
            // Output: Shipping adjustments: 1

            // Total effect: +15.00 (shipping) -10.00 (voucher) = +5.00
            echo 'Total adjustment: ' . $adjustments->getTotal()->getDecimalString() . "\n";
            // Output: Total adjustment: 5.00

            // Final order gross: 100.00 + 5.00 = 105.00
            $orderGross = $basketGross->add($adjustments->getTotal());
            echo 'Order gross total: ' . $orderGross->getDecimalString() . "\n";
            // Output: Order gross total: 105.00

            // Shipping has tax; voucher does not
            echo 'Net impact of adjustments: ' . $adjustments->getNetTotal()->getDecimalString() . "\n";
            echo 'Tax impact of adjustments: ' . $adjustments->getTaxTotal()->getDecimalString() . "\n";

            // Discount total is the positive magnitude of reducing adjustments
            echo 'Discount total (for invoice): ' . $adjustments->getDiscountTotal()->getDecimalString() . "\n";
            // Output: Discount total (for invoice): 10.00
        }

        public function exampleFreeShippingVoucher(): void
        {
            // A voucher that waives shipping does not reach into the shipping adjustment.
            // It simply emits a discount that negates it.

            $basketGross = Money::fromDecimalString('100.00');

            // Shipping already calculated
            $shippingCost = Money::fromDecimalString('15.00');
            $shippingAdjustment = new CheckoutAdjustment(
                AdjustmentType::SHIPPING,
                'core.shipping',
                'DHL',
                $shippingCost,
                0.19
            );

            $adjustments = new AdjustmentCollection($shippingAdjustment);

            // Voucher runs later, sees the shipping, and emits a discount to match it
            $voucherDiscount = new CheckoutAdjustment(
                AdjustmentType::DISCOUNT,
                'core.voucher',
                'Free Shipping Voucher',
                Money::fromDecimalString('-15.00') // Negate the shipping cost
            );

            $adjustments = $adjustments->with($voucherDiscount);

            // Total is now zero; shipping is free
            echo 'Final shipping effect: ' . $adjustments->getTotal()->getDecimalString() . "\n";
            // Output: Final shipping effect: 0.00

            // Order total: 100.00 + 0.00 = 100.00
            $orderGross = $basketGross->add($adjustments->getTotal());
            echo 'Order gross: ' . $orderGross->getDecimalString() . "\n";
            // Output: Order gross: 100.00
        }
    }

Why Adjustments, Not Direct Order Writes?
===========================================

-   **Composability:** Features do not know about each other. Shipping, vouchers, credit points, and
    custom addons all contribute adjustments in sequence. Each can see and offset what came before.
-   **Immutability:** The adjustment collection is immutable. A provider cannot accidentally mutate
    another provider's contribution; each receives the collection as-is and returns a new one.
-   **Auditability:** Every change to what the customer pays is an explicit adjustment in the ledger.
    Backends and integrations can inspect the collection to understand why the total is what it is.
-   **Atomic safety:** All adjustments land with the order, and if anything fails during placement,
    none of them do. Partial orders are impossible.
-   **Invoice stability:** Because labels are denormalized, invoices generated years later still make sense,
    even if the extension that produced an adjustment has been uninstalled.
