<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressBasket;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressShippingQuote;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressShippingQuoteOption;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingQuoteService;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;

/**
 * Answers an express wallet's live shipping-rate callback: given the signed basket snapshot and the
 * (street-redacted) destination the wallet supplied, it reuses the same {@see ShippingQuoteService} the
 * normal checkout runs on, so the options and costs a customer sees in the wallet sheet are the ones they
 * would see in the shop - one source of truth, not a parallel express-only path.
 */
#[Autoconfigure(public: true)]
final class ExpressShippingQuoteService
{
    public function __construct(
        private readonly ShippingQuoteService $shippingQuoteService
    ) {}

    public function quote(ExpressBasket $basket, Address $address, ProductsConfiguration $configuration): ExpressShippingQuote
    {
        $context = $basket->toShippingContext($address->getCountry(), $address->getZip());
        $goodsTotal = $basket->getTotalGross();

        $options = array_map(
            static fn(ShippingOption $option): ExpressShippingQuoteOption => new ExpressShippingQuoteOption(
                $option->getKey(),
                $option->getLabel(),
                $option->getCost(),
                Money::fromCents($goodsTotal->getCents() + $option->getCost()->getCents()),
                $option->getDeliveryEstimate()
            ),
            $this->shippingQuoteService->getAvailableOptions($configuration, $context)
        );

        return new ExpressShippingQuote($basket->getCurrency(), $goodsTotal, $options);
    }
}
