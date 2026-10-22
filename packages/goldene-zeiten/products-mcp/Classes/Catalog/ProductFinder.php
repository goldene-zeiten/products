<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Visibility\ProductVisibilityResolver;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * Finds a product the way the frontend shows it: in the storage folder of the site, in the site language,
 * not hidden, and visible according to the registered visibility checks.
 */
final readonly class ProductFinder
{
    public function __construct(
        private ProductRepository $productRepository,
        private ProductVisibilityResolver $visibilityResolver,
        private StorageScope $storageScope
    ) {}

    public function find(int $uid, McpContext $context): ?Product
    {
        $query = $this->productRepository->createQuery();
        $this->storageScope->apply($query, $context->site);
        $query->matching($query->equals('uid', $uid));
        $product = $query->execute()->getFirst();
        if (!$product instanceof Product || !$this->visibilityResolver->isVisible($product, $context->request)) {
            return null;
        }

        return $product;
    }

    /**
     * @throws ToolFailedException
     */
    public function require(int $uid, McpContext $context): Product
    {
        $product = $this->find($uid, $context);
        if ($product === null) {
            throw new ToolFailedException(sprintf('There is no product with the uid %d.', $uid), 1792710230);
        }

        return $product;
    }
}
