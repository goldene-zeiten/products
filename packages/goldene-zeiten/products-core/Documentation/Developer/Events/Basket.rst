..  include:: /Includes.rst.txt
..  _developer-events-basket:

======
Basket
======

Events fired during basket operations and item resolution.

BasketUpdatedEvent
------------------

Lets integrators adjust the whole basket right after its contents changed and before it is
persisted - add a surcharge line, enforce bundle rules, or apply per-customer constraints the
standard price providers cannot express.

Mutable: Yes (via ``setBasket(Basket $basket)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class AdjustBasketOnUpdate
    {
        public function __invoke(BasketUpdatedEvent $event): void
        {
            $basket = $event->getBasket();
            // Adjust basket contents, add surcharges, or enforce rules
            $event->setBasket($basket);
        }
    }

ModifyBasketItemEvent
---------------------

Lets integrators adjust a single basket line as it is resolved for display and checkout -
surcharges, per-customer pricing or bundle rules the standard price providers cannot express.

Mutable: Yes (via ``setViewItem(BasketViewItem $viewItem)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class ApplyPerCustomerPricing
    {
        public function __invoke(ModifyBasketItemEvent $event): void
        {
            $viewItem = $event->getViewItem();
            // Adjust item prices, quantities, or other properties
            $event->setViewItem($viewItem);
        }
    }
