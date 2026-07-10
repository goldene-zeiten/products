<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Shipping;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\HandlingFee;
use GoldeneZeiten\Products\Domain\Repository\HandlingFeeRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A third cost bucket alongside shipping/payment (legacy tt_products modeled handling
 * separately from shipping). Unlike shipping, handling is never a shopper choice - the first
 * applicable fee for the basket/country is resolved automatically, same as tax. Stateless by
 * design - takes an already-resolved ProductsConfiguration rather than reading settings itself,
 * so it's a pure function of its inputs (see ProductsConfiguration's docblock).
 */
final class HandlingFeeService
{
    public function __construct(
        private readonly HandlingFeeRepository $handlingFeeRepository,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    /**
     * $request (when given) applies the shopper's FE-usergroup/personal discount to the resolved
     * fee, same reasoning and mechanism as ShippingCostService::resolveSelection().
     */
    public function resolveCost(ProductsConfiguration $configuration, BasketViewModel $basketViewModel, string $countryCode, ?ServerRequestInterface $request = null): Money
    {
        if (!$configuration->isHandlingEnabled()) {
            return Money::fromCents(0);
        }
        $method = $this->resolveApplicable($basketViewModel, $countryCode);
        $cost = $method?->getRate() ?? Money::fromCents(0);
        return $request !== null ? $cost->discountByPercent($this->frontendUserResolver->getDiscountPercent($request)) : $cost;
    }

    private function resolveApplicable(BasketViewModel $basketViewModel, string $countryCode): ?HandlingFee
    {
        $weight = $this->calculateWeight($basketViewModel);
        $goodsTotal = $basketViewModel->getTotalGross();
        foreach ($this->handlingFeeRepository->findApplicableForCountry($countryCode) as $candidate) {
            if ($candidate->isApplicable($weight, $goodsTotal)) {
                return $candidate;
            }
        }
        return null;
    }

    private function calculateWeight(BasketViewModel $basketViewModel): int
    {
        $weight = 0;
        foreach ($basketViewModel->getItems() as $item) {
            $weight += $item->getProduct()->getWeight() * $item->getQuantity();
        }
        return $weight;
    }
}
