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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class TaxServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/tax_rates.csv');
    }

    #[Test]
    #[DataProvider('taxRateProvider')]
    public function getTaxRateReturnsTheExpectedFraction(string $requestedCountry, float $expected): void
    {
        $subject = $this->get(TaxService::class);

        $this->assertSame($expected, $subject->getTaxRate($this->configuration('DE'), $this->taxClass(), $requestedCountry));
    }

    public static function taxRateProvider(): \Generator
    {
        // Must convert whole percentage (19.00) from fixture to fraction (0.19) for multiplication.
        yield 'returns a fraction not the stored whole percentage' => ['requestedCountry' => 'DE', 'expected' => 0.19];
        yield 'falls back to the default country when the requested country has no rate' => ['requestedCountry' => 'FR', 'expected' => 0.19];
    }

    /**
     * A found TaxRate row with rate=0.00 (e.g. the seeded `zero` tax class) is a deliberate,
     * valid configuration and must NOT throw - it is not the same thing as "no row was found".
     */
    #[Test]
    public function getTaxRateReturnsZeroWithoutThrowingForAConfiguredZeroRate(): void
    {
        $subject = $this->get(TaxService::class);

        $this->assertSame(0.0, $subject->getTaxRate($this->configuration('DE'), $this->zeroTaxClass(), 'DE'));
    }

    #[Test]
    #[DataProvider('missingTaxRateProvider')]
    public function getTaxRateThrowsWhenNoRateCanBeResolved(string $defaultCountry, bool $useTaxClass, ?string $requestedCountry, int $expectedExceptionCode): void
    {
        $subject = $this->get(TaxService::class);
        $this->expectException(MissingTaxRateException::class);
        $this->expectExceptionCode($expectedExceptionCode);

        $subject->getTaxRate($this->configuration($defaultCountry), $useTaxClass ? $this->taxClass() : null, $requestedCountry);
    }

    public static function missingTaxRateProvider(): \Generator
    {
        yield 'a null tax class' => ['defaultCountry' => 'DE', 'useTaxClass' => false, 'requestedCountry' => null, 'expectedExceptionCode' => 1783758015];
        yield 'neither the requested nor default country has a rate' => ['defaultCountry' => 'AT', 'useTaxClass' => true, 'requestedCountry' => 'FR', 'expectedExceptionCode' => 1783758016];
    }

    private function taxClass(): TaxClass
    {
        $taxClass = $this->get(TaxClassRepository::class)->findByUid(1);
        $this->assertInstanceOf(TaxClass::class, $taxClass);
        return $taxClass;
    }

    private function zeroTaxClass(): TaxClass
    {
        $zeroTaxClass = $this->get(TaxClassRepository::class)->findByUid(2);
        $this->assertInstanceOf(TaxClass::class, $zeroTaxClass);
        return $zeroTaxClass;
    }

    private function configuration(string $defaultCountry): ProductsConfiguration
    {
        return new ProductsConfiguration($defaultCountry, 'gross', 'EUR', false, Money::fromCents(0), false, 'none', 900);
    }
}
