<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Repository\TaxClassRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\TaxService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TaxServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private TaxClass $taxClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tax_rates.csv');
        $taxClass = $this->get(TaxClassRepository::class)->findByUid(1);
        self::assertInstanceOf(TaxClass::class, $taxClass);
        $this->taxClass = $taxClass;
    }

    #[Test]
    public function getTaxRateReturnsZeroForANullTaxClass(): void
    {
        self::assertSame(0.0, $this->get(TaxService::class)->getTaxRate($this->configuration('DE'), null));
    }

    #[Test]
    public function getTaxRateReturnsAFractionNotTheStoredWholePercentage(): void
    {
        // The fixture stores rate=19.00 (a 19% whole percentage, as edited in the backend) -
        // getTaxRate() must convert it to 0.19 for direct "1 + rate" multiplication.
        self::assertSame(0.19, $this->get(TaxService::class)->getTaxRate($this->configuration('DE'), $this->taxClass, 'DE'));
    }

    #[Test]
    public function getTaxRateFallsBackToTheDefaultCountryWhenTheRequestedCountryHasNoRate(): void
    {
        self::assertSame(0.19, $this->get(TaxService::class)->getTaxRate($this->configuration('DE'), $this->taxClass, 'FR'));
    }

    #[Test]
    public function getTaxRateReturnsZeroWhenNeitherTheRequestedNorDefaultCountryHasARate(): void
    {
        self::assertSame(0.0, $this->get(TaxService::class)->getTaxRate($this->configuration('AT'), $this->taxClass, 'FR'));
    }

    private function configuration(string $defaultCountry): ProductsConfiguration
    {
        return new ProductsConfiguration($defaultCountry, 'gross', 'EUR', false, Money::fromCents(0), false, 'none');
    }
}
