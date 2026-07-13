<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Domain\Dto\OrderTrackingLink;
use GoldeneZeiten\Products\Event\ModifyOrderTrackingEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class OrderTrackingListener
{
    public static int $invocationCount = 0;
    public static bool $enabled = false;

    public function __invoke(ModifyOrderTrackingEvent $event): void
    {
        self::$invocationCount++;
        if (!self::$enabled) {
            return;
        }

        $event->addTrackingLink(new OrderTrackingLink('Track my parcel', 'https://carrier.example/track/EVT-1'));
    }
}
