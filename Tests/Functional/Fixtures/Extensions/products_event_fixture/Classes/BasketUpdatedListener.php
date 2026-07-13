<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

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
        $basket->addVoucherCode('EVENT-TOUCHED');
        $event->setBasket($basket);
    }
}
