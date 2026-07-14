..  include:: /Includes.rst.txt
..  _developer-api-shipping-providers:

====================
Shipping Providers API
====================

The Shipping Providers API enables integrators to implement carrier integrations for DHL, FedEx,
UPS, regional couriers, pickup points, or any custom logistics partner the shop needs. A shipping
provider is a service that receives a basket, decides what it can carry, and offers one or more
shipping options with prices. Because shipping is inherently shop-specific, **the extension ships
with only table-rate shipping** (static shipping methods maintained in the backend); integrators
add carriers by implementing the interface.

**Location:** :php:`GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface`

Who Decides What
================

The core of the Shipping Provider API is a **clear division of responsibility**:

**The Carrier Decides What It Can Carry**
    Only the carrier knows its own weight caps, service zones, and goods restrictions. The
    :php:`quote()` method returns only options it can actually serve. When it cannot serve a
    basket, it returns an empty array — never a list of reasons why. **The extension never
    second-guesses the carrier.** If DHL refuses a :code:`hazmat` item, it returns :code:`[]`.
    The checkout sees no DHL options at all for that basket.

**The Customer Decides Which Option to Use**
    Every carrier's options are pooled into one flat list. The customer sees "Standard (9.90 EUR)",
    "Express (24.90 EUR)", "Pickup Point (5.00 EUR)" and picks one. The choice is identified by
    a composite key, not a provider identifier alone (see `Option Keys`_ below).

**The Shop Decides the Preselection and the Surcharge**
    The shop controls which option is preselected (none, cheapest, or a specific key via
    :code:`products.shipping.preselect`). The shop also decides whether to add charges on top of
    the carrier's rate — today, a bulky-goods surcharge for oversized items. These charges stay
    separate from the carrier's quote (see `Money: Carrier Rate vs Shop Surcharge`_ below).

One Carrier Offers Many Options
================================

This is what separates shipping from payment. A payment method is one choice: the customer picks
Stripe or PayPal and pays once. A shipping carrier offers multiple options: the customer picks
Standard, Express, or Pickup Point from the same carrier.

Because all carriers' options are pooled, the options of different carriers live in one list and
the customer does not see which carrier each came from. **The option identifier must be composite:**
:code:`"provider:option"` — for example, :code:`"dhl:express"`, :code:`"tablerate:12"`, :code:`"pickup:point_42"`.

..  _option-keys:

Option Keys
-----------

The provider identifier is registered in :php:`getIdentifier()`. The option identifier is the
carrier's own business — it may be a record UID (as the built-in table-rate carrier does), a code,
or any unique string. The extension never interprets it beyond splitting on the colon.

Use :php:`ShippingOption::splitKey()` to decompose a key into its parts, and
:php:`ShippingOption::getKey()` to compose one from provider and option identifiers.

Registration
============

A class implementing :php:`ShippingProviderInterface` is automatically registered — no manual
entry in :file:`Configuration/Services.yaml` is required. The interface itself carries the
:php:`#[AutoconfigureTag('products.shipping_provider')]` attribute, so Symfony's autowiring
discovers and collects all implementations.

**Interface Methods:**

:php:`getIdentifier(): string`
    A unique identifier for this carrier (e.g., :code:`'dhl'`, :code:`'fedex'`, :code:`'pickup_network'`).
    Used to resolve which carrier an option came from.

:php:`getPriority(): int`
    Higher values are offered first. Carriers with the same priority keep their registration
    order. Use :code:`0` for default priority. The built-in table-rate carrier returns :code:`0`,
    so an integrator's carrier can rank above it by using a higher value. Example: :code:`100`
    for a primary carrier, :code:`10` for secondary.

:php:`quote(ShippingContext $context): ShippingOption[]`
    **Discovery phase:** Return the shipping options this carrier can serve for the given basket.
    Return an empty array if the carrier cannot serve this basket (e.g., too heavy, unsupported
    country, or contains goods the carrier refuses). **Never return partial options or explain
    why; either return options or return nothing.** The extension handles the "no options available"
    error (see `No Carrier Can Serve the Basket`_ below).

:php:`resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption`
    **Execution phase:** The customer chose an option; re-quote it now against the basket as it
    stands. Return the :php:`ShippingOption` if it is still valid, or :code:`null` if the basket
    changed and the option no longer applies (e.g., the weight increased, or a restricted item
    was added). If your carrier does not restrict options by weight or goods, you may re-return
    the same option from :php:`quote()`.

The Context: Everything a Carrier May Decide On
================================================

:php:`ShippingContext` is an immutable value object containing everything a carrier may need to
decide whether it can serve a basket. **It carries the items, not just totals**, because a carrier
caps by parcel weight, not total weight, and refuses whole classes of goods.

:php:`getItems(): ShippingContextItem[]`
    The lines of the basket. Each item has:

    -   :php:`getQuantity(): int` — How many units.
    -   :php:`getWeight(): int` — Weight of a single unit, in grams. (Total weight = quantity × unit weight.)
    -   :php:`isBulky(): bool` — Whether this item is oversized, triggering shop surcharges.
    -   :php:`getShippingClass(): string` — The goods classification (see `Shipping Classes`_ below).

:php:`getTotalWeight(): int`
    Sum of (quantity × unit weight) for all items, in grams. Carriers use this to check weight caps.

:php:`getGoodsTotal(): Money`
    The basket's gross value before adjustments. Carriers use this to check minimum order values
    or to quote by value rather than weight.

:php:`getCurrency(): string`
    The shop currency (e.g., :code:`'EUR'`). Carriers quote in this currency.

:php:`getCountryCode(): string`
    The customer's shipping country (ISO 3166-1 alpha-2, e.g., :code:`'DE'`, :code:`'US'`).
    Carriers use this to check service availability.

:php:`getPostCode(): string`
    The shipping postcode. Empty until the customer enters an address. Carriers price by zone,
    not just by country, so the postcode matters even when the country alone would suffice.

:php:`getFrontendUserUid(): int`
    The UID of the logged-in frontend user (0 if guest). Carriers can use this to offer
    different rates to different customer groups (e.g., wholesale vs. retail).

:php:`getShippingClasses(): array<string>`
    The distinct shipping classes present in the basket (see `Shipping Classes`_ below).

Shipping Classes
================

A product carries a **shipping class** field:

:php:`Product.shipping_class`
    Defined in TCA with options: :code:`''` (default, no restriction), :code:`'hazmat'` (hazardous
    goods), :code:`'freight'` (freight-only), :code:`'refrigerated'` (temperature-controlled).

**Important:** The extension DEFINES the field but NEVER interprets it. A carrier matches the class
against what it is willing to carry and ignores classes it does not know. If a carrier does not
recognize :code:`'refrigerated'`, it simply ignores that class and applies its own logic to the
item.

**Example:** A carrier that refuses :code:`'hazmat'` checks :php:`$context->getShippingClasses()`
and returns :code:`[]` if :code:`'hazmat'` is present:

..  code-block:: php

    public function quote(ShippingContext $context): array
    {
        // Refuse baskets containing hazardous goods
        if (in_array('hazmat', $context->getShippingClasses())) {
            return [];
        }

        // ... proceed to quote other options
    }

No Carrier Can Serve the Basket
================================

When the checkout calls :php:`ShippingQuoteService::resolveSelection()` with a customer's selected
option key, the service resolves the option via the registry. **If the key no longer resolves**
(the carrier was uninstalled, or the basket changed and the option became invalid), the service
throws:

:php:`NoShippingOptionAvailableException` (code 1784073620)
    An order that nobody can deliver must not be payable. The checkout stops, and the shipping
    step shows an error to the customer. The basket may have changed since the option was chosen
    (an item was added, a restricted class was included, or the carrier's extension was uninstalled).

**Empty keys are not an error.** An empty key means the customer has not chosen yet, or shipping
is disabled. :php:`resolveSelection()` returns a "no shipping" selection in that case.

Money: Carrier Rate vs Shop Surcharge
======================================

The carrier quotes a rate; the shop may add charges. These are kept **strictly apart** because
they have different semantics:

**Carrier Rate** (:code:`core.shipping`)
    What the carrier charges for transport. A :php:`CheckoutAdjustment` of type :php:`SHIPPING`
    with provider :code:`'core.shipping'`. This is taxable. **A free-shipping voucher waives this.**

**Shop Surcharge** (:code:`core.shipping.surcharge`)
    What the shop charges on top — today, a bulky-goods surcharge for oversized items. A
    :php:`CheckoutAdjustment` of type :php:`SHIPPING` with provider :code:`'core.shipping.surcharge'`.
    This is also taxable. **A free-shipping voucher does NOT waive this**: handling an oversized
    item costs the shop the same whether the customer or a voucher pays for the transport.

**Why This Separation Matters:**

A free-shipping voucher offsets the carrier adjustment (emits a negative discount equal to the
carrier's rate) rather than suppressing it. This means:

-   The order records what shipping actually cost (the carrier's rate).
-   The order records what the voucher paid for (a negative discount).
-   The customer pays the same either way (no shipping cost to them), but the shop's ledger is complete.
-   The shop's own surcharge remains: a voucher pays for transport, not for handling.

In other words, the voucher sees the carrier adjustment in the collection, matches its amount,
and emits an equal negative adjustment. The voucher stays ignorant of shipping details; it simply
negates an adjustment it can see.

Order Storage
=============

An order records three shipping-related fields:

:php:`Order.shipping_provider`
    The provider identifier (e.g., :code:`'dhl'`, :code:`'tablerate'`).

:php:`Order.shipping_option`
    The option identifier (e.g., :code:`'express'`, :code:`'12'`). Together with the provider,
    this forms the composite key.

:php:`Order.shipping_label`
    The human-readable label of the chosen option (e.g., :code:`'DHL Express 5-7 days'`).
    **Intentionally denormalized** — the order must render correctly even after the carrier's
    extension is uninstalled.

There is no foreign key to a shipping record, because an API-based carrier has no record in the
shop's database. The label is the only link between the order and what the carrier called the
option.

Filtering and Reordering: ShippingOptionsCollectedEvent
=======================================================

After the registry collects all available options from all carriers, it dispatches
:php:`ShippingOptionsCollectedEvent`. **This event is NOT how you register a carrier** —
registration happens by implementing the interface.

The event exists to let listeners **reorder or filter** the already-collected list. Common use
cases:

-   Hide express shipping for wholesale customers.
-   Promote a specific pickup point to the top for this region.
-   Remove a carrier that failed a pre-flight check (e.g., quota exhausted).

The event is **mutable** via :php:`setOptions()`. See the example below.

Example: Complete Carrier Implementation
==========================================

This example implements a fictional carrier that ships to EU countries only, refuses hazmat,
and quotes by weight:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Shipping;

    use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
    use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
    use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
    use GoldeneZeiten\Products\Core\Shipping\ShippingProviderInterface;

    /**
     * Example carrier: ships to EU only, refuses hazmat, quotes by weight.
     */
    final class ExampleCarrierShippingProvider implements ShippingProviderInterface
    {
        public function getIdentifier(): string
        {
            return 'example_carrier';
        }

        public function getPriority(): int
        {
            return 10; // Offered above default table-rate
        }

        public function quote(ShippingContext $context): array
        {
            // Service area: EU only
            $euCountries = ['AT', 'BE', 'DE', 'DK', 'ES', 'FI', 'FR', 'GR', 'IE', 'IT', 'LU',
                'NL', 'PL', 'PT', 'SE', 'CZ', 'RO'];
            if (!in_array($context->getCountryCode(), $euCountries, true)) {
                return [];
            }

            // Refuse baskets with hazardous goods
            if (in_array('hazmat', $context->getShippingClasses(), true)) {
                return [];
            }

            // Weight cap: 20 kg maximum
            if ($context->getTotalWeight() > 20000) {
                return [];
            }

            // Minimum order value: 10.00 EUR
            if ($context->getGoodsTotal()->getCents() < 1000) {
                return [];
            }

            // Quote by weight brackets
            $weight = $context->getTotalWeight();
            $options = [];

            // Standard: 0-5kg
            if ($weight <= 5000) {
                $options[] = new ShippingOption(
                    $this->getIdentifier(),
                    'standard',
                    'Standard (3-5 business days)',
                    Money::fromDecimalString('5.99')
                );
            }

            // Express: 0-10kg
            if ($weight <= 10000) {
                $options[] = new ShippingOption(
                    $this->getIdentifier(),
                    'express',
                    'Express (Next business day)',
                    Money::fromDecimalString('12.99')
                );
            }

            // Freight: all weights (but must quote separately)
            if ($weight > 5000) {
                $options[] = new ShippingOption(
                    $this->getIdentifier(),
                    'freight',
                    'Freight (5-7 business days)',
                    Money::fromDecimalString('24.99')
                );
            }

            return $options;
        }

        public function resolve(string $optionIdentifier, ShippingContext $context): ?ShippingOption
        {
            // Re-quote all options and find the one matching the identifier.
            // Simpler: for a stateless carrier like this, just re-quote and filter.
            foreach ($this->quote($context) as $option) {
                if ($option->getOptionIdentifier() === $optionIdentifier) {
                    return $option;
                }
            }

            return null;
        }
    }

Listening to ShippingOptionsCollectedEvent
===========================================

To reorder or filter options after discovery (e.g., promote pickup points for a region),
attach a listener:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Core\Event\ShippingOptionsCollectedEvent;
    use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

    #[AsEventListener]
    final class PromotePickupPointsForRegion
    {
        public function __invoke(ShippingOptionsCollectedEvent $event): void
        {
            $countryCode = $event->getContext()->getCountryCode();

            // For German customers, promote pickup points to the top
            if ($countryCode === 'DE') {
                $options = $event->getOptions();

                // Partition: pickup points first, others second
                $pickupPoints = array_filter(
                    $options,
                    fn($opt) => str_contains($opt->getLabel(), 'Pickup')
                );
                $others = array_filter(
                    $options,
                    fn($opt) => !str_contains($opt->getLabel(), 'Pickup')
                );

                $event->setOptions(array_values(array_merge($pickupPoints, $others)));
            }
        }
    }

Why This API Is an Interface, Not an Event
===========================================

-   **Discovery question:** "Which carriers can serve this basket?" Events cannot answer this —
    a listener is optional and cannot decide what to offer.
-   **Resolution by key:** The checkout selected :code:`"dhl:express"`. The registry must resolve
    it to the actual carrier and option. Events cannot do this; only a service registry can.
-   **Fail-closed guarantees:** A forgotten event listener silently does nothing. A forgotten
    implementation is obvious in the codebase and fails immediately at discovery time.
-   **Immutability of contract:** Each carrier defines its own availability, priority, and options.
    These cannot change based on listener order; the interface makes the contract explicit.

The event (:php:`ShippingOptionsCollectedEvent`) exists to allow last-minute filtering and
reordering — for cases where the choice to hide or promote an option is made at discovery time,
not at implementation time.
