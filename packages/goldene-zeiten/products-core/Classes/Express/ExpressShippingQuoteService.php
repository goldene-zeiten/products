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
use GoldeneZeiten\Products\Core\Express\Exception\ExpressOrderExceptionInterface;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementExceptionInterface;
use GoldeneZeiten\Products\Core\Shipping\ShippingQuoteService;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Site\Entity\Site;

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
        private readonly ShippingQuoteService $shippingQuoteService,
        private readonly BasketService $basketService,
        private readonly ExpressOrderService $orderService
    ) {}

    /**
     * Pass the site of the callback request, so carriers find their per-site configuration.
     */
    public function quote(ExpressBasket $basket, Address $address, ProductsConfiguration $configuration, ?Site $site = null): ExpressShippingQuote
    {
        $context = $basket->toShippingContext($address->getCountry(), $address->getZip(), $site);
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

    /**
     * Replaces each option's order total - goods plus carrier - with the total the order would really be
     * charged, which also holds the handling and payment fees, discounts and rounding. It is the quote
     * {@see ExpressOrderService::quote()} gives the order that is placed, for the customer's own basket, so the
     * wallet sheet displays the amount that is authorized. An option the order could not be placed with is
     * dropped. Without a basket in the customer's session the quote stays as it is.
     */
    public function withOrderTotals(ExpressShippingQuote $quote, ServerRequestInterface $request, ExpressCheckoutProviderInterface $provider, Address $address): ExpressShippingQuote
    {
        $basket = $this->basketService->getBasketViewModel($request, $address->getCountry());
        if ($basket->getItems() === []) {
            return $quote;
        }

        $options = [];
        foreach ($quote->getOptions() as $option) {
            try {
                $totals = $this->orderService->quote($request, $provider, $basket, $address, $option->getKey());
            } catch (OrderPlacementExceptionInterface|ExpressOrderExceptionInterface) {
                continue;
            }
            $options[] = $option->withOrderTotal($totals->getTotalGross());
        }

        return new ExpressShippingQuote($quote->getCurrency(), $quote->getGoodsTotal(), $options);
    }
}
