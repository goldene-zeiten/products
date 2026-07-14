<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Domain\Dto\Shipping\ShippingContextItem;

/**
 * Builds the immutable view of a basket that carriers decide on, so a carrier never has to reach into the
 * basket, the request or the session itself.
 */
final class ShippingContextFactory
{
    public function createFromBasket(BasketViewModel $basketViewModel, Address $address, int $frontendUserUid = 0): ShippingContext
    {
        return new ShippingContext(
            array_map($this->toItem(...), $basketViewModel->getItems()),
            $basketViewModel->getTotalWeight(),
            $basketViewModel->getTotalGross(),
            $basketViewModel->getCurrency(),
            $address->getCountry(),
            $address->getZip(),
            $frontendUserUid
        );
    }

    private function toItem(BasketViewItem $viewItem): ShippingContextItem
    {
        return new ShippingContextItem(
            $viewItem->getQuantity(),
            $viewItem->getWeight(),
            $viewItem->isBulky(),
            $viewItem->getProduct()->getShippingClass()
        );
    }
}
