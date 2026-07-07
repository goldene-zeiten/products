<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\Exception\RepositoryIsReadOnlyException;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;

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

    /**
     * @test
     */
    public function productCanBeRetrieved(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shop.csv');

        $product = $this->productRepository->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        self::assertSame('Product 1', $product->getTitle());
    }

    /**
     * @test
     */
    public function relatedAndAccessoryProductsAreResolved(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shop.csv');

        $product = $this->productRepository->findByUid(1);
        self::assertInstanceOf(Product::class, $product);

        $relatedTitles = array_map(static fn(Product $p): string => $p->getTitle(), $product->getRelatedProducts()->toArray());
        $accessoryTitles = array_map(static fn(Product $p): string => $p->getTitle(), $product->getAccessoryProducts()->toArray());

        self::assertSame(['Product 2'], $relatedTitles);
        self::assertSame(['Product 3'], $accessoryTitles);
    }

    /**
     * @test
     */
    public function productWithoutRelationsHasEmptyCrossSellSets(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/shop.csv');

        $product = $this->productRepository->findByUid(2);
        self::assertInstanceOf(Product::class, $product);

        self::assertCount(0, $product->getRelatedProducts());
        self::assertCount(0, $product->getAccessoryProducts());
    }

    /**
     * @test
     */
    public function addThrowsReadOnlyException(): void
    {
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode(1751741001);

        $this->productRepository->add(new Product());
    }

    /**
     * @test
     */
    public function updateThrowsReadOnlyException(): void
    {
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode(1751741002);

        $this->productRepository->update(new Product());
    }

    /**
     * @test
     */
    public function removeThrowsReadOnlyException(): void
    {
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode(1751741003);

        $this->productRepository->remove(new Product());
    }

    /**
     * @test
     */
    public function removeAllThrowsReadOnlyException(): void
    {
        $this->expectException(RepositoryIsReadOnlyException::class);
        $this->expectExceptionCode(1751741004);

        $this->productRepository->removeAll();
    }
}
