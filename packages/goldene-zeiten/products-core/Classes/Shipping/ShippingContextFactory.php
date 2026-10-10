<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Builds the immutable view of a basket that carriers decide on, so a carrier never has to reach into the
 * basket, the request or the session itself.
 */
final class ShippingContextFactory
{
    /**
     * Pass the site of the request where it is at hand, so carriers find their per-site configuration
     * without depending on the globally published request.
     */
    public function createFromBasket(BasketViewModel $basketViewModel, Address $address, int $frontendUserUid = 0, ?Site $site = null): ShippingContext
    {
        return new ShippingContext(
            array_map($this->toItem(...), $basketViewModel->getItems()),
            $basketViewModel->getTotalWeight(),
            $basketViewModel->getTotalGross(),
            $basketViewModel->getCurrency(),
            $address->getCountry(),
            $address->getZip(),
            $frontendUserUid,
            $site
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
