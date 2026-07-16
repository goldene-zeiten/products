<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressBasket;
use GoldeneZeiten\Products\Core\Shipping\ShippingContextFactory;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Snapshots the live basket an express button was rendered for into the {@see ExpressBasket} that is signed
 * into the per-basket token {@see ExpressBasketTokenService}. It reuses {@see ShippingContextFactory} for
 * the item mapping, so the weights, shipping classes and totals travelling in the token are exactly the ones
 * the in-shop shipping quote sees - the destination is the only part still missing, and the wallet supplies
 * that on the shipping-rate callback.
 *
 * Every express provider's button plugin needs the same snapshot, so it lives in core next to the token
 * service rather than in one provider.
 */
#[Autoconfigure(public: true)]
final class ExpressBasketFactory
{
    public function __construct(
        private readonly ShippingContextFactory $shippingContextFactory
    ) {}

    public function createFromBasket(BasketViewModel $basketViewModel, int $frontendUserUid = 0): ExpressBasket
    {
        $context = $this->shippingContextFactory->createFromBasket($basketViewModel, new Address(), $frontendUserUid);

        return new ExpressBasket(
            $context->getItems(),
            $context->getTotalWeight(),
            $context->getGoodsTotal(),
            $context->getCurrency(),
            $frontendUserUid
        );
    }
}
