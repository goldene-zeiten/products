<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Repository\TaxClassRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Exception\MissingTaxRateException;
use GoldeneZeiten\Products\Service\TaxService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class TaxServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private TaxClass $taxClass;
    private TaxClass $zeroTaxClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tax_rates.csv');
        $taxClassRepository = $this->get(TaxClassRepository::class);
        $taxClass = $taxClassRepository->findByUid(1);
        self::assertInstanceOf(TaxClass::class, $taxClass);
        $this->taxClass = $taxClass;
        $zeroTaxClass = $taxClassRepository->findByUid(2);
        self::assertInstanceOf(TaxClass::class, $zeroTaxClass);
        $this->zeroTaxClass = $zeroTaxClass;
    }

    #[Test]
    public function getTaxRateThrowsForANullTaxClass(): void
    {
        $this->expectException(MissingTaxRateException::class);
        $this->expectExceptionCode(1783758015);

        $this->get(TaxService::class)->getTaxRate($this->configuration('DE'), null);
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

    /**
     * A found TaxRate row with rate=0.00 (e.g. the seeded `zero` tax class) is a deliberate,
     * valid configuration and must NOT throw - it is not the same thing as "no row was found".
     */
    #[Test]
    public function getTaxRateReturnsZeroWithoutThrowingForAConfiguredZeroRate(): void
    {
        self::assertSame(0.0, $this->get(TaxService::class)->getTaxRate($this->configuration('DE'), $this->zeroTaxClass, 'DE'));
    }

    #[Test]
    public function getTaxRateThrowsWhenNeitherTheRequestedNorDefaultCountryHasARate(): void
    {
        $this->expectException(MissingTaxRateException::class);
        $this->expectExceptionCode(1783758016);

        $this->get(TaxService::class)->getTaxRate($this->configuration('AT'), $this->taxClass, 'FR');
    }

    private function configuration(string $defaultCountry): ProductsConfiguration
    {
        return new ProductsConfiguration($defaultCountry, 'gross', 'EUR', false, Money::fromCents(0), false, 'none');
    }
}
