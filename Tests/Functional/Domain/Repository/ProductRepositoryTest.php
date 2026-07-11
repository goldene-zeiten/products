<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Domain\Repository\Exception\RepositoryIsReadOnlyException;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class ProductRepositoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private ProductRepository $productRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->get(ProductRepository::class);
    }

    #[Test]
    public function productCanBeRetrieved(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shop.csv');

        $product = $this->productRepository->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame('Product 1', $product->getTitle());
    }

    #[Test]
    public function relatedAndAccessoryProductsAreResolved(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shop.csv');

        $product = $this->productRepository->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);

        $relatedTitles = array_map(static fn(Product $p): string => $p->getTitle(), $product->getRelatedProducts()->toArray());
        $accessoryTitles = array_map(static fn(Product $p): string => $p->getTitle(), $product->getAccessoryProducts()->toArray());

        $this->assertSame(['Product 2'], $relatedTitles);
        $this->assertSame(['Product 3'], $accessoryTitles);
    }

    #[Test]
    public function productWithoutRelationsHasEmptyCrossSellSets(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shop.csv');

        $product = $this->productRepository->findByUid(2);
        $this->assertInstanceOf(Product::class, $product);

        $this->assertCount(0, $product->getRelatedProducts());
        $this->assertCount(0, $product->getAccessoryProducts());
    }

    #[Test]
    public function countByCategoryCountsOnlyDirectMembers(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shop.csv');

        $category = $this->get(CategoryRepository::class)->findByUid(1);
        $this->assertInstanceOf(Category::class, $category);

        $this->assertSame(1, $this->productRepository->countByCategory($category));
    }

    #[Test]
    public function addThrowsReadOnlyException(): void
    {
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode(1751741001);

        $this->productRepository->add(new Product());
    }

    #[Test]
    public function updateThrowsReadOnlyException(): void
    {
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode(1751741002);

        $this->productRepository->update(new Product());
    }

    #[Test]
    public function removeThrowsReadOnlyException(): void
    {
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode(1751741003);

        $this->productRepository->remove(new Product());
    }

    #[Test]
    public function removeAllThrowsReadOnlyException(): void
    {
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode(1751741004);

        $this->productRepository->removeAll();
    }
}
