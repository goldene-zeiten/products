<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Model\TaxRate;
use GoldeneZeiten\Products\Domain\Repository\TaxClassRepository;
use GoldeneZeiten\Products\Domain\Repository\TaxRateRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The fixture deliberately places tax class/rate records on pid 99 - a page unrelated to any
 * storagePid a test's TypoScript setup might configure - to prove findByTaxClassAndCountry()
 * resolves records regardless of storage page (it's a shared, non-page-bound lookup table, same
 * reasoning as ShippingMethodRepository's own storage-page-independent queries).
 */
final class TaxRateRepositoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private TaxRateRepository $subject;
    private TaxClass $taxClass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/tax_rates.csv');
        $this->subject = $this->get(TaxRateRepository::class);
        $taxClass = $this->get(TaxClassRepository::class)->findByUid(1);
        $this->assertInstanceOf(TaxClass::class, $taxClass);
        $this->taxClass = $taxClass;
    }

    #[Test]
    public function findByTaxClassAndCountryFindsARateRegardlessOfItsStoragePage(): void
    {
        $rate = $this->subject->findByTaxClassAndCountry($this->taxClass, 'DE', new \DateTimeImmutable());

        $this->assertInstanceOf(TaxRate::class, $rate);
        $this->assertSame(19.0, $rate->getRate());
    }

    #[Test]
    public function findByTaxClassAndCountryReturnsNullForAnUnconfiguredCountry(): void
    {
        $this->assertNull($this->subject->findByTaxClassAndCountry($this->taxClass, 'FR', new \DateTimeImmutable()));
    }

    #[Test]
    public function findByTaxClassAndCountryFallsBackToTheAnyCountryWildcardRow(): void
    {
        $reducedTaxClass = $this->get(TaxClassRepository::class)->findByUid(3);
        $this->assertInstanceOf(TaxClass::class, $reducedTaxClass);

        $rate = $this->subject->findByTaxClassAndCountry($reducedTaxClass, 'AT', new \DateTimeImmutable());

        $this->assertInstanceOf(TaxRate::class, $rate);
        $this->assertSame(7.0, $rate->getRate());
    }

    #[Test]
    public function findByTaxClassAndCountryPrefersACountrySpecificRowOverTheWildcard(): void
    {
        $reducedTaxClass = $this->get(TaxClassRepository::class)->findByUid(3);
        $this->assertInstanceOf(TaxClass::class, $reducedTaxClass);

        $rate = $this->subject->findByTaxClassAndCountry($reducedTaxClass, 'FR', new \DateTimeImmutable());

        $this->assertInstanceOf(TaxRate::class, $rate);
        $this->assertSame(5.5, $rate->getRate());
    }
}
