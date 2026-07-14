..  include:: /Includes.rst.txt
..  _developer-events-shipping:

========
Shipping
========

Events fired during shipping option collection.

ShippingOptionsCollectedEvent
-----------------------------

Lets integrators reorder or hide the shipping options the checkout is about to offer — promote a
pickup point, or drop express shipping for wholesale customers. Registering a carrier is done by
implementing :php:`ShippingProviderInterface` (see :ref:`developer-api-shipping-providers`); this
event only post-filters the options the carriers already offered. Listeners can call
``setOptions()`` to replace the options list.

Mutable: Yes (via ``setOptions(ShippingOption[] $options)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class PromotePickupPoint
    {
        public function __invoke(ShippingOptionsCollectedEvent $event): void
        {
            $context = $event->getContext();
            $options = $event->getOptions();
            // Reorder, filter, or adjust options based on customer region or cart contents
            $event->setOptions($options);
        }
    }
