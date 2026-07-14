<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Lets integrators adjust a single basket line as it is resolved for display and checkout -
 * surcharges, per-customer pricing or bundle rules the standard price providers cannot express.
 * Mutable via {@see ModifyBasketItemEvent::setViewItem()}.
 *
 * @see BasketService::resolveItem()
 */
final class ModifyBasketItemEvent
{
    public function __construct(
        private BasketViewItem $viewItem,
        private readonly ServerRequestInterface $request
    ) {}

    public function getViewItem(): BasketViewItem
    {
        return $this->viewItem;
    }

    public function setViewItem(BasketViewItem $viewItem): void
    {
        $this->viewItem = $viewItem;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
