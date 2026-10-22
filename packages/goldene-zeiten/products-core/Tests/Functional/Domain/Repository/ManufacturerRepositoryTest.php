<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\Manufacturer;
use GoldeneZeiten\Products\Core\Domain\Repository\ManufacturerRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ManufacturerRepositoryTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/../../Controller/Fixtures/ManufacturerListTest/manufacturers.csv');
    }

    #[Test]
    public function aCategoryWithoutManufacturedProductsListsNoManufacturer(): void
    {
        $subject = $this->get(ManufacturerRepository::class);

        $this->assertSame([], $subject->findWithProductsInCategories([[999]], [2]));
    }

    #[Test]
    public function theStorageFolderIsGivenByTheCaller(): void
    {
        $subject = $this->get(ManufacturerRepository::class);

        $manufacturers = $subject->findWithProductsInCategories([], [2]);

        $this->assertContains('Alpha Rings', array_map(static fn(Manufacturer $manufacturer): string => $manufacturer->getBrandName(), $manufacturers));
    }
}
