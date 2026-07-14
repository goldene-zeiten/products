<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Domain\Dto\BasketItem;
use GoldeneZeiten\Products\Event\BasketUpdatedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class BasketUpdatedListener
{
    public static int $invocationCount = 0;

    public function __invoke(BasketUpdatedEvent $event): void
    {
        self::$invocationCount++;
        $basket = $event->getBasket();
        // Mutate the basket to prove the dispatched basket is mutable
        $basket->addItem(new BasketItem(9999, null, 1));
        $event->setBasket($basket);
    }
}
