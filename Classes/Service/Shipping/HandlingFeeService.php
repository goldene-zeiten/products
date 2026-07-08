<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Shipping;

use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\HandlingFee;
use GoldeneZeiten\Products\Domain\Repository\HandlingFeeRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

/**
 * A third cost bucket alongside shipping/payment (legacy tt_products modeled handling
 * separately from shipping). Unlike shipping, handling is never a shopper choice - the first
 * applicable fee for the basket/country is resolved automatically, same as tax.
 */
final class HandlingFeeService
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly HandlingFeeRepository $handlingFeeRepository,
        ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    public function isEnabled(): bool
    {
        return (bool)($this->settings['handling']['enabled'] ?? false);
    }

    public function resolveCost(BasketViewModel $basketViewModel, string $countryCode): Money
    {
        if (!$this->isEnabled()) {
            return Money::fromCents(0);
        }
        $method = $this->resolveApplicable($basketViewModel, $countryCode);
        return $method?->getRate() ?? Money::fromCents(0);
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
