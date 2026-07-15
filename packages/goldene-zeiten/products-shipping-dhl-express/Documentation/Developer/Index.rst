:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

===================================
Developer / Extension Points
===================================

This extension has no backend module and no Extbase controllers of its own — its public surface is a small
set of PSR-14 events, fired from
:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Rating\HttpDhlExpressRatingClient` and
:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Shipping\DhlExpressShippingProvider`.

..  contents:: Table of contents
    :local:

..  _developer-modify-rate-request:

ModifyDhlExpressRateRequestEvent
====================================

:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Event\ModifyDhlExpressRateRequestEvent` is dispatched by
:php:`HttpDhlExpressRatingClient` just before a rate request is sent to DHL Express.
:php:`getParameters()` / :php:`setParameters()` expose the request array — the associative
:php:`array<string, string>` serialised to the DHL ``GET /rates`` query string, as built by
:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Rating\DhlExpressRateRequestBuilder`. :php:`getContext()`
returns the basket's :php:`ShippingContext` and :php:`getConfiguration()` the resolved
:php:`DhlExpressConfiguration` — both read-only, for a listener that needs to decide *how* to adjust the
request.

Use it to set a real destination city (the builder sends the postcode as the city — see
:ref:`Destination city and package dimensions <configuration-destination-and-dimensions>`), send real
package dimensions instead of the small-parcel default, request a specific product, or override
``isCustomsDeclarable`` — anything the shop's own configuration does not already cover.

Mutable: Yes (via :php:`setParameters(array $parameters)`)

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/SetRealDestinationCityListener.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Shipping\DhlExpress\Event\ModifyDhlExpressRateRequestEvent;
    use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

    #[AsEventListener]
    final class SetRealDestinationCityListener
    {
        public function __invoke(ModifyDhlExpressRateRequestEvent $event): void
        {
            $parameters = $event->getParameters();
            $parameters['destinationCityName'] = $this->lookUpCityForBasket($event->getContext());
            $event->setParameters($parameters);
        }

        private function lookUpCityForBasket(\GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext $context): string
        {
            // e.g. resolved from the customer's address record instead of the postcode-only basket context.
            return 'Bonn';
        }
    }

..  _developer-modify-shipping-options:

ModifyDhlExpressShippingOptionsEvent
========================================

:php:`GoldeneZeiten\Products\Shipping\DhlExpress\Event\ModifyDhlExpressShippingOptionsEvent` is dispatched
by :php:`DhlExpressShippingProvider` after the DHL products have been mapped to core :php:`ShippingOption`
objects (and already filtered by the `products.shipping.dhlexpress.usedProducts` allow-list and the
currency guard), but before they are returned from :php:`quote()`. :php:`getOptions()` /
:php:`setOptions()` expose that :php:`ShippingOption[]`; :php:`getContext()` and :php:`getConfiguration()`
are the same read-only basket context and resolved configuration
:ref:`ModifyDhlExpressRateRequestEvent <developer-modify-rate-request>` gets.

Use it to drop a product DHL itself does not restrict, reorder options, or relabel/surcharge one —
anything specific to DHL Express's own options, before they are pooled with every other carrier's. Core's
own :php:`ShippingOptionsCollectedEvent` (EXT:products_core) fires afterwards, once all carriers' options —
DHL Express's included — have been pooled into the one list the checkout actually shows; use that one
instead for adjustments that should apply across every carrier, not just DHL Express.

Mutable: Yes (via :php:`setOptions(ShippingOption[] $options)`)

..  code-block:: php
    :caption: EXT:my_extension/Classes/EventListener/DropEconomyForRushOrdersListener.php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Shipping\DhlExpress\Event\ModifyDhlExpressShippingOptionsEvent;
    use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

    #[AsEventListener]
    final class DropEconomyForRushOrdersListener
    {
        public function __invoke(ModifyDhlExpressShippingOptionsEvent $event): void
        {
            if (!in_array('rush', $event->getContext()->getShippingClasses(), true)) {
                return;
            }

            // ECONOMY SELECT (product code U) is too slow for a rush order.
            $options = array_values(array_filter(
                $event->getOptions(),
                static fn($option): bool => $option->getOptionIdentifier() !== 'U',
            ));
            $event->setOptions($options);
        }
    }
