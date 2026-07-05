<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class ProductRepositoryTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'typo3conf/ext/products',
    ];

    private ?ProductRepository $productRepository = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->productRepository = $this->get(ProductRepository::class);
    }

    /**
     * @test
     */
    public function productCanBePersistedAndRetrieved(): void
    {
        $product = new Product();
        $product->setTitle('Test Product');
        $product->setSlug('test-product');
        $product->setPid(1);

        $this->productRepository->add($product);
        $this->get(\TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager::class)->persistAll();

        $retrievedProduct = $this->productRepository->findByUid($product->getUid());
        self::assertInstanceOf(Product::class, $retrievedProduct);
        self::assertSame('Test Product', $retrievedProduct->getTitle());
    }
}
