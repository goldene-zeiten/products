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

..  _developer-events-basket-line-item-options:

ResolveLineItemOptionsEvent
---------------------------

Turns the option selections of a basket line - an engraving, a ring size - into the options the line is
sold with. The add-to-basket form submits them as ``options[<identifier>]``; core renders no option fields
itself, an add-on overrides the partial ``Product/LineItemOptions`` to add them.

The event is dispatched for every line, with or without selections, when it is added and every time the
basket is resolved. A listener can therefore refuse a line that lacks a required option, and labels and
surcharges always follow the current option definitions. Lines with different selections are separate
basket lines.

*   ``addOption(LineItemOption $option)`` accepts one selection. Its ``value`` is stored as the selection,
    so resolving that stored value again must return the same ``value``; ``valueLabel`` is what the customer
    reads. The surcharge is per unit and in the shop's pricing mode,
    like product prices. A personalizing option makes the line custom-made goods without a withdrawal
    right.
*   ``reject(string $reason)`` refuses the selections. Adding fails with the reason as an error message;
    a stored line that is rejected later leaves the basket.

Selections no listener resolves are not stored. Without a listener a line has no options.

The options of every line are rendered in the basket, the checkout review, the order mails and the
invoice through the partial ``LineItem/Options``, and stored on the order item
(``OrderItem::getLineOptions()``).

Mutable: Yes (via ``addOption()`` and ``reject()``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class ResolveEngraving
    {
        public function __invoke(ResolveLineItemOptionsEvent $event): void
        {
            $engraving = trim($event->getSelections()['engraving'] ?? '');
            if ($engraving === '') {
                return;
            }
            if (mb_strlen($engraving) > 20) {
                $event->reject('The engraving may have at most 20 characters.');
                return;
            }
            $event->addOption(new LineItemOption(
                'engraving',
                'Engraving',
                $engraving,
                $engraving,
                Money::fromDecimalString('9.90'),
                true,
            ));
        }
    }
