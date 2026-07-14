<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Shipping;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Model\HandlingFee;
use GoldeneZeiten\Products\Core\Domain\Repository\HandlingFeeRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use Psr\Http\Message\ServerRequestInterface;

final class HandlingFeeService
{
    public function __construct(
        private readonly HandlingFeeRepository $handlingFeeRepository,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    /**
     * Applies FE-usergroup discount to fee.
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
        $weight = $basketViewModel->getTotalWeight();
        $goodsTotal = $basketViewModel->getTotalGross();
        foreach ($this->handlingFeeRepository->findApplicableForCountry($countryCode) as $candidate) {
            if ($candidate->isApplicable($weight, $goodsTotal)) {
                return $candidate;
            }
        }
        return null;
    }
}
