<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Shipping;

use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\ShippingSelection;
use GoldeneZeiten\Products\Domain\Model\ShippingMethod;
use GoldeneZeiten\Products\Domain\Repository\ShippingMethodRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Shipping\Exception\NoShippingMethodAvailableException;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class ShippingCostService
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly ShippingMethodRepository $shippingMethodRepository,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function isEnabled(): bool
    {
        return (bool)($this->settings['shipping']['enabled'] ?? false);
    }

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
    public function resolveAvailable(BasketViewModel $basketViewModel, string $countryCode): array
    {
        if (!$this->isEnabled()) {
            return [];
        }
        $weight = $this->calculateWeight($basketViewModel);
        $goodsTotal = $basketViewModel->getTotalGross();
        $candidates = $this->shippingMethodRepository->findApplicableForCountry($countryCode);
        return array_values(array_filter(
            $candidates,
            static fn(ShippingMethod $method): bool => $method->isApplicable($weight, $goodsTotal)
        ));
    }

    /**
     * Re-validates the shopper's earlier choice against the current basket/country rather than
     * trusting the session blindly, same reasoning as vouchers being fully re-resolved at
     * placement time. $waived comes from the caller since it depends on which voucher (if any)
     * ends up applied, resolved earlier in the same placement.
     *
     * @throws NoShippingMethodAvailableException
     */
    public function resolveSelection(int $shippingMethodUid, BasketViewModel $basketViewModel, string $countryCode, bool $waived): ShippingSelection
    {
        if (!$this->isEnabled() || $shippingMethodUid === 0) {
            return ShippingSelection::none();
        }
        $method = $this->findSelectedMethod($shippingMethodUid, $basketViewModel, $countryCode);
        $rate = $waived ? Money::fromCents(0) : $method->getRate();
        $cost = $rate->add($this->calculateBulkySurcharge($basketViewModel));
        return new ShippingSelection($method, $cost);
    }

    /**
     * A free-shipping voucher waives the method's own rate, not the bulky surcharge - an
     * oversized item still costs extra to handle regardless of who pays the base shipping rate.
     */
    private function calculateBulkySurcharge(BasketViewModel $basketViewModel): Money
    {
        $surchargePerUnit = Money::fromDecimalString((string)($this->settings['shipping']['bulkySurcharge'] ?? '0.00'));
        if ($surchargePerUnit->getCents() === 0) {
            return Money::fromCents(0);
        }
        $bulkyUnits = 0;
        foreach ($basketViewModel->getItems() as $item) {
            if ($this->isBulky($item)) {
                $bulkyUnits += $item->getQuantity();
            }
        }
        return $surchargePerUnit->multiply((float)$bulkyUnits);
    }

    private function isBulky(BasketViewItem $item): bool
    {
        return $item->getProduct()->isBulky() || ($item->getArticle()?->isBulky() ?? false);
    }

    private function findSelectedMethod(int $shippingMethodUid, BasketViewModel $basketViewModel, string $countryCode): ShippingMethod
    {
        foreach ($this->resolveAvailable($basketViewModel, $countryCode) as $method) {
            if ($method->getUid() === $shippingMethodUid) {
                return $method;
            }
        }
        throw new NoShippingMethodAvailableException(
            sprintf('Shipping method %d is not available for the current basket and country "%s".', $shippingMethodUid, $countryCode),
            1783600000
        );
    }

    /**
     * Articles inherit the product's weight, there is no per-article override.
     */
    private function calculateWeight(BasketViewModel $basketViewModel): int
    {
        $weight = 0;
        foreach ($basketViewModel->getItems() as $item) {
            $weight += $item->getProduct()->getWeight() * $item->getQuantity();
        }
        return $weight;
    }
}
