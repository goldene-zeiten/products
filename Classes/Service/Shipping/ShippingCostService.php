<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Shipping;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\ShippingSelection;
use GoldeneZeiten\Products\Domain\Dto\Checkout\ShippingSelectionCriteria;
use GoldeneZeiten\Products\Domain\Model\ShippingMethod;
use GoldeneZeiten\Products\Domain\Repository\ShippingMethodRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Shipping\Exception\NoShippingMethodAvailableException;
use GoldeneZeiten\Products\Service\TaxService;
use Psr\Http\Message\ServerRequestInterface;

final class ShippingCostService
{
    public function __construct(
        private readonly ShippingMethodRepository $shippingMethodRepository,
        private readonly TaxService $taxService,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function findMethod(int $shippingMethodUid): ?ShippingMethod
    {
        if ($shippingMethodUid === 0) {
            return null;
        }
        $method = $this->shippingMethodRepository->findByUid($shippingMethodUid);
        return $method instanceof ShippingMethod ? $method : null;
    }

    /**
     * @return ShippingMethod[]
     */
    public function resolveAvailable(ProductsConfiguration $configuration, BasketViewModel $basketViewModel, string $countryCode): array
    {
        if (!$configuration->isShippingEnabled()) {
            return [];
        }
        $weight = $basketViewModel->getTotalWeight();
        $goodsTotal = $basketViewModel->getTotalGross();
        $candidates = $this->shippingMethodRepository->findApplicableForCountry($countryCode);
        return array_values(array_filter(
            $candidates,
            static fn(ShippingMethod $method): bool => $method->isApplicable($weight, $goodsTotal)
        ));
    }

    /**
     * Re-validates choice against current basket/country; applies FE-usergroup discount to rate, not surcharge.
     *
     * @throws NoShippingMethodAvailableException
     */
    public function resolveSelection(ProductsConfiguration $configuration, ShippingSelectionCriteria $criteria, ?ServerRequestInterface $request = null): ShippingSelection
    {
        if (!$configuration->isShippingEnabled() || $criteria->getShippingMethodUid() === 0) {
            return ShippingSelection::none();
        }
        $method = $this->findSelectedMethod($configuration, $criteria);
        $rate = $criteria->isWaived() ? Money::fromCents(0) : $this->applyDiscount($method->getRate(), $request);
        $cost = $rate->add($this->calculateBulkySurcharge($configuration, $criteria->getBasketViewModel()));
        $taxRate = $this->taxService->getShippingTaxRate($configuration, $method->getEffectiveTaxRateOverride(), $criteria->getCountryCode());
        return new ShippingSelection($method, $cost, $taxRate);
    }

    private function applyDiscount(Money $amount, ?ServerRequestInterface $request): Money
    {
        if ($request === null) {
            return $amount;
        }
        return $amount->discountByPercent($this->frontendUserResolver->getDiscountPercent($request));
    }

    /**
     * A free-shipping voucher waives the method's own rate, not the bulky surcharge - an
     * oversized item still costs extra to handle regardless of who pays the base shipping rate.
     */
    private function calculateBulkySurcharge(ProductsConfiguration $configuration, BasketViewModel $basketViewModel): Money
    {
        $surchargePerUnit = $configuration->getBulkySurcharge();
        if ($surchargePerUnit->getCents() === 0) {
            return Money::fromCents(0);
        }
        $bulkyUnits = 0;
        foreach ($basketViewModel->getItems() as $item) {
            if ($item->isBulky()) {
                $bulkyUnits += $item->getQuantity();
            }
        }
        return $surchargePerUnit->multiply($bulkyUnits);
    }

    private function findSelectedMethod(ProductsConfiguration $configuration, ShippingSelectionCriteria $criteria): ShippingMethod
    {
        foreach ($this->resolveAvailable($configuration, $criteria->getBasketViewModel(), $criteria->getCountryCode()) as $method) {
            if ($method->getUid() === $criteria->getShippingMethodUid()) {
                return $method;
            }
        }
        throw new NoShippingMethodAvailableException(
            sprintf('Shipping method %d is not available for the current basket and country "%s".', $criteria->getShippingMethodUid(), $criteria->getCountryCode()),
            1783600000
        );
    }
}
