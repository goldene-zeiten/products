<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\Exception\RepositoryIsReadOnlyException;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ProductRepositoryTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function productCanBeRetrieved(): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $product = $productRepository->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        $this->assertSame('Product 1', $product->getTitle());
    }

    #[Test]
    public function relatedAndAccessoryProductsAreResolved(): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $product = $productRepository->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);

        $relatedTitles = array_map(static fn(Product $p): string => $p->getTitle(), $product->getRelatedProducts()->toArray());
        $accessoryTitles = array_map(static fn(Product $p): string => $p->getTitle(), $product->getAccessoryProducts()->toArray());

        $this->assertSame(['Product 2'], $relatedTitles);
        $this->assertSame(['Product 3'], $accessoryTitles);
    }

    #[Test]
    public function productWithoutRelationsHasEmptyCrossSellSets(): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $product = $productRepository->findByUid(2);
        $this->assertInstanceOf(Product::class, $product);

        $this->assertCount(0, $product->getRelatedProducts());
        $this->assertCount(0, $product->getAccessoryProducts());
    }

    #[Test]
    public function countByCategoryCountsOnlyDirectMembers(): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $category = $this->get(CategoryRepository::class)->findByUid(1);
        $this->assertInstanceOf(Category::class, $category);

        $this->assertSame(1, $productRepository->countByCategory($category));
    }

    /**
     * @param \Closure(ProductRepository): void $mutate
     */
    #[Test]
    #[DataProvider('mutatorThrowsReadOnlyExceptionProvider')]
    public function mutatorThrowsReadOnlyException(\Closure $mutate, int $expectedExceptionCode): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode($expectedExceptionCode);

        $mutate($productRepository);
    }

    public static function mutatorThrowsReadOnlyExceptionProvider(): \Generator
    {
        yield 'add throws read-only exception' => [
            'mutate' => static function (ProductRepository $repository): void {
                $repository->add(new Product());
            },
            'expectedExceptionCode' => 1751741001,
        ];

        yield 'update throws read-only exception' => [
            'mutate' => static function (ProductRepository $repository): void {
                $repository->update(new Product());
            },
            'expectedExceptionCode' => 1751741002,
        ];

        yield 'remove throws read-only exception' => [
            'mutate' => static function (ProductRepository $repository): void {
                $repository->remove(new Product());
            },
            'expectedExceptionCode' => 1751741003,
        ];

        yield 'removeAll throws read-only exception' => [
            'mutate' => static function (ProductRepository $repository): void {
                $repository->removeAll();
            },
            'expectedExceptionCode' => 1751741004,
        ];
    }

    #[Test]
    public function findOffersReturnsBothOffers(): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $offers = $productRepository->findOffers();
        $this->assertCount(2, $offers);

        $titles = array_map(static fn(Product $p): string => $p->getTitle(), $offers);
        $this->assertContains('Product 2', $titles);
        $this->assertContains('Product 4', $titles);
    }

    #[Test]
    public function findHighlightsReturnsBothHighlights(): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $highlights = $productRepository->findHighlights();
        $this->assertCount(2, $highlights);

        $titles = array_map(static fn(Product $p): string => $p->getTitle(), $highlights);
        $this->assertContains('Product 3', $titles);
        $this->assertContains('Product 4', $titles);
    }

    #[Test]
    public function findNewReturnsProductsOrderedByCreationDateDescending(): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $new = $productRepository->findNew(36500);
        $this->assertCount(6, $new);

        $titles = array_map(static fn(Product $p): string => $p->getTitle(), $new);
        // Verify ordering: newest first (descending by crdate)
        // Product 6 and 5 are newest, Product 1 is oldest
        $this->assertSame('Product 6', $titles[0]);
        $this->assertSame('Product 1', $titles[5]);
    }

    #[Test]
    public function findNewReturnsAllProductsWhenDaysIsLarge(): void
    {
        $productRepository = $this->get(ProductRepository::class);
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));

        $new = $productRepository->findNew(36500);
        $this->assertCount(6, $new);
    }
}
