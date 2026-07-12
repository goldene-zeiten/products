<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Model\TaxRate;
use GoldeneZeiten\Products\Domain\Repository\TaxClassRepository;
use GoldeneZeiten\Products\Domain\Repository\TaxRateRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Fixture places records on an unrelated pid to prove lookups are storage-page-independent.
 */
final class TaxRateRepositoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/tax_rates.csv');
    }

    #[Test]
    #[DataProvider('findByTaxClassAndCountryProvider')]
    public function findByTaxClassAndCountry(int $taxClassUid, string $country, ?float $expectedRate): void
    {
        $subject = $this->get(TaxRateRepository::class);
        $taxClass = $this->get(TaxClassRepository::class)->findByUid($taxClassUid);
        $this->assertInstanceOf(TaxClass::class, $taxClass);

        $rate = $subject->findByTaxClassAndCountry($taxClass, $country, new \DateTimeImmutable());

        if ($expectedRate === null) {
            $this->assertNull($rate);
            return;
        }
        $this->assertInstanceOf(TaxRate::class, $rate);
        $this->assertSame($expectedRate, $rate->getRate());
    }

    public static function findByTaxClassAndCountryProvider(): \Generator
    {
        yield 'finds a rate regardless of its storage page' => [
            'taxClassUid' => 1,
            'country' => 'DE',
            'expectedRate' => 19.0,
        ];

        yield 'returns null for an unconfigured country' => [
            'taxClassUid' => 1,
            'country' => 'FR',
            'expectedRate' => null,
        ];

        yield 'falls back to the any-country wildcard row' => [
            'taxClassUid' => 3,
            'country' => 'AT',
            'expectedRate' => 7.0,
        ];

        yield 'prefers a country-specific row over the wildcard' => [
            'taxClassUid' => 3,
            'country' => 'FR',
            'expectedRate' => 5.5,
        ];
    }
}
