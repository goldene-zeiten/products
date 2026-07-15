<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Lets integrators adjust the whole basket right after its contents changed and before it is
 * persisted - add a surcharge line, enforce bundle rules, or apply per-customer constraints the
 * standard price providers cannot express. Mutable via {@see BasketUpdatedEvent::setBasket()}.
 *
 * @see BasketService::dispatchBasketUpdated()
 */
final class BasketUpdatedEvent
{
    public function __construct(
        private Basket $basket,
        private readonly ServerRequestInterface $request
    ) {}

    public function getBasket(): Basket
    {
        return $this->basket;
    }

    public function setBasket(Basket $basket): void
    {
        $this->basket = $basket;
    }

    public function getRequest(): ServerRequestInterface
    {
        return $this->request;
    }
}
