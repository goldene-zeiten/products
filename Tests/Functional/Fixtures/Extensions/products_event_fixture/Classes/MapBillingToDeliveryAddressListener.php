<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Domain\Model\OrderAddress;
use GoldeneZeiten\Products\Event\MapBillingToDeliveryAddressEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class MapBillingToDeliveryAddressListener
{
    public static int $invocationCount = 0;
    public static bool $enabled = false;

    public function __invoke(MapBillingToDeliveryAddressEvent $event): void
    {
        self::$invocationCount++;
        if (!self::$enabled) {
            return;
        }

        $deliveryAddress = new OrderAddress();
        $deliveryAddress->setAddressType('delivery');
        $deliveryAddress->setFirstName('EVENT-DELIVERY');
        $deliveryAddress->setLastName($event->getBillingAddress()->getLastName());
        $deliveryAddress->setStreet($event->getBillingAddress()->getStreet());
        $deliveryAddress->setZip($event->getBillingAddress()->getZip());
        $deliveryAddress->setCity($event->getBillingAddress()->getCity());
        $deliveryAddress->setCountry($event->getBillingAddress()->getCountry());
        $event->setDeliveryAddress($deliveryAddress);
    }
}
