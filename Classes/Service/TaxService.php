<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Repository\TaxClassRepository;
use GoldeneZeiten\Products\Domain\Repository\TaxRateRepository;
use GoldeneZeiten\Products\Service\Exception\MissingTaxRateException;

/**
 * Stateless by design - takes an already-resolved ProductsConfiguration rather than reading
 * settings itself, so it's a pure function of its inputs (see ProductsConfiguration's docblock).
 */
final class TaxService
{
    private const STANDARD_TAX_CLASS_CODE = 'standard';

    public function __construct(
        private readonly TaxRateRepository $taxRateRepository,
        private readonly TaxClassRepository $taxClassRepository
    ) {}

    /**
     * Returns the rate as a fraction (e.g. 0.19 for 19%), ready for direct `1 + rate`
     * multiplication - TaxRate::rate itself stores the whole percentage (19.00, editable as such
     * in the backend), the /100 conversion happens here so every caller gets a usable multiplier.
     *
     * A found TaxRate row with rate = 0.00 (e.g. the seeded `zero` tax class) is a deliberate,
     * valid configuration and returns 0.0 like any other rate - it must never be confused with "no
     * row was found", which throws instead. Every real product has a mandatory tax_class FK, so a
     * null $taxClass reaching here is itself a data-integrity problem, not an intentionally
     * tax-free product (those use the `zero` tax class with a real TaxRate row).
     */
    public function getTaxRate(ProductsConfiguration $configuration, ?TaxClass $taxClass, ?string $countryCode = null): float
    {
        if ($taxClass === null) {
            throw new MissingTaxRateException('No tax class given; cannot resolve a tax rate.', 1783758015);
        }

        $defaultCountry = $configuration->getDefaultCountry();
        $countryCode ??= $defaultCountry;
        $now = new \DateTimeImmutable();

        $taxRate = $this->taxRateRepository->findByTaxClassAndCountry($taxClass, $countryCode, $now);

        // Fallback to default country if not found for requested country
        if ($taxRate === null && $countryCode !== $defaultCountry) {
            $taxRate = $this->taxRateRepository->findByTaxClassAndCountry($taxClass, $defaultCountry, $now);
        }

        if ($taxRate === null) {
            throw new MissingTaxRateException(
                sprintf('No tax rate configured for tax class %d in country %s (or the default country).', (int)$taxClass->getUid(), $countryCode),
                1783758016
            );
        }

        return $taxRate->getRate() / 100;
    }

    /**
     * Shipping has no tax class of its own - it inherits the "standard" tax class's rate by
     * default (mirroring legacy tt_products' default behaviour), unless a shipping method
     * explicitly overrides it (a whole percentage, e.g. 19.0 for 19%, converted to a fraction here
     * same as getTaxRate()).
     */
    public function getShippingTaxRate(ProductsConfiguration $configuration, ?float $overridePercent, ?string $countryCode = null): float
    {
        if ($overridePercent !== null) {
            return $overridePercent / 100;
        }
        return $this->getTaxRate($configuration, $this->standardTaxClass(), $countryCode);
    }

    private function standardTaxClass(): ?TaxClass
    {
        return $this->taxClassRepository->findOneByCode(self::STANDARD_TAX_CLASS_CODE);
    }
}
