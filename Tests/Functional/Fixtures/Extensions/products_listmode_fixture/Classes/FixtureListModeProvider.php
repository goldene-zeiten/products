<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ListModeFixture;

use GoldeneZeiten\Products\Catalog\ProductListModeProviderInterface;
use GoldeneZeiten\Products\Domain\Dto\Catalog\ProductListContext;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;

/**
 * Fixture product listing provider with a static list of products.
 * Proves an EXTERNAL product list mode provider reaches the product-list content element
 * through the contract, and that it can supply both products and the label the editor
 * chooses.
 */
final class FixtureListModeProvider implements ProductListModeProviderInterface
{
    public function __construct(
        private readonly ProductRepository $productRepository
    ) {}

    public function getMode(): string
    {
        return 'fixture-featured';
    }

    public function getLabel(): string
    {
        return 'Fixture featured';
    }

    /**
     * @return \GoldeneZeiten\Products\Domain\Model\Product[]
     */
    public function findProducts(ProductListContext $context): array
    {
        // Return products with uid 1 and 2
        return array_filter(
            $this->productRepository->findAllIgnoringStoragePage(),
            static fn($product) => in_array($product->getUid(), [1, 2], true)
        );
    }
}
