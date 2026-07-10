<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service;

use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Repository\TaxRateRepository;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;

final class TaxService
{
    /**
     * @var array<string, mixed>
     */
    private array $settings;

    public function __construct(
        private readonly TaxRateRepository $taxRateRepository,
        private readonly ConfigurationManagerInterface $configurationManager
    ) {
        $this->settings = $this->configurationManager->getConfiguration(
            ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS,
            'Products'
        );
    }

    /**
     * Returns the rate as a fraction (e.g. 0.19 for 19%), ready for direct `1 + rate`
     * multiplication - TaxRate::rate itself stores the whole percentage (19.00, editable as such
     * in the backend), the /100 conversion happens here so every caller gets a usable multiplier.
     */
    public function getTaxRate(?TaxClass $taxClass, ?string $countryCode = null): float
    {
        if ($taxClass === null) {
            return 0.0;
        }

        $defaultCountry = (string)($this->settings['tax']['defaultCountry'] ?? 'DE');
        $countryCode ??= $defaultCountry;
        $now = new \DateTimeImmutable();

        $taxRate = $this->taxRateRepository->findByTaxClassAndCountry($taxClass, $countryCode, $now);

        // Fallback to default country if not found for requested country
        if ($taxRate === null && $countryCode !== $defaultCountry) {
            $taxRate = $this->taxRateRepository->findByTaxClassAndCountry($taxClass, $defaultCountry, $now);
        }

        return $taxRate !== null ? $taxRate->getRate() / 100 : 0.0;
    }

    public function getPricingMode(): string
    {
        return (string)($this->settings['pricing']['mode'] ?? 'gross');
    }

    public function getCurrency(): string
    {
        return (string)($this->settings['pricing']['currency'] ?? 'EUR');
    }
}
