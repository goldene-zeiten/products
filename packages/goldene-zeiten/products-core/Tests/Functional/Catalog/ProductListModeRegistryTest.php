<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Catalog;

use GoldeneZeiten\Products\Core\Catalog\ProductListModeRegistry;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListContext;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;

final class ProductListModeRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-listmode-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductListModeRegistryTest/products.csv');
    }

    #[Test]
    public function registryHasFixtureFeaturedProvider(): void
    {
        $registry = $this->get(ProductListModeRegistry::class);

        $this->assertTrue($registry->has('fixture-featured'));
        $this->assertFalse($registry->has('nope'));
    }

    #[Test]
    public function findProductsReturnsFixtureFeaturedProducts(): void
    {
        $registry = $this->get(ProductListModeRegistry::class);
        $request = new ServerRequest('http://localhost/');
        $context = new ProductListContext($request);

        $products = $registry->findProducts('fixture-featured', $context);

        $this->assertCount(2, $products);
        $uids = array_map(static fn($p) => $p->getUid(), $products);
        sort($uids);
        $this->assertSame([1, 2], $uids);
    }

    #[Test]
    public function getSelectItemsContainsFixtureFeatured(): void
    {
        $registry = $this->get(ProductListModeRegistry::class);

        $items = $registry->getSelectItems();

        $modes = array_column($items, 'value');
        $this->assertContains('fixture-featured', $modes);

        // Check that 'fixture-featured' has the correct label
        $fixtureItems = array_filter($items, static fn($item) => $item['value'] === 'fixture-featured');
        $this->assertCount(1, $fixtureItems);
        $fixtureItem = reset($fixtureItems);
        $this->assertIsArray($fixtureItem);
        $this->assertSame('Fixture featured', $fixtureItem['label']);
    }
}
