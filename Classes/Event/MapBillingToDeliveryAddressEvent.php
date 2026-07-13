<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\OrderAddress;
use GoldeneZeiten\Products\Service\Order\OrderFactory;

/**
 * Lets integrators derive or adjust the delivery address from the billing address while the order
 * is built. Useful for copying billing into delivery when the customer gave none, or normalising
 * it for a carrier.
 *
 * Mutable via inline {@see MapBillingToDeliveryAddressEvent::setDeliveryAddress()}
 *
 * @see OrderFactory::create()
 */
final class MapBillingToDeliveryAddressEvent
{
    public function __construct(
        private readonly OrderAddress $billingAddress,
        private ?OrderAddress $deliveryAddress,
        private readonly Order $order
    ) {}

    public function getBillingAddress(): OrderAddress
    {
        return $this->billingAddress;
    }

    public function getDeliveryAddress(): ?OrderAddress
    {
        return $this->deliveryAddress;
    }

    public function setDeliveryAddress(?OrderAddress $deliveryAddress): void
    {
        $this->deliveryAddress = $deliveryAddress;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}
