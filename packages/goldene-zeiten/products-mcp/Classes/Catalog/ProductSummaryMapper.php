<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Manufacturer;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Pricing\VariantPriceRangeResolver;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;

/**
 * The short description of a product a search result lists.
 */
final readonly class ProductSummaryMapper
{
    public function __construct(
        private PriceMapper $priceMapper,
        private ProductUrlFactory $urlFactory,
        private VariantPriceRangeResolver $priceRangeResolver
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function map(Product $product, McpContext $context): array
    {
        $manufacturer = $product->getManufacturer();
        $category = $product->getCategories()->toArray()[0] ?? null;

        return [
            'uid' => $product->getUid(),
            'title' => $product->getTitle(),
            'itemNumber' => $product->getItemNumber(),
            'price' => $this->priceMapper->forProduct($product, $context),
            'inStock' => $this->isInStock($product, $context),
            'manufacturer' => $manufacturer instanceof Manufacturer ? $manufacturer->getBrandName() : '',
            'category' => $category instanceof Category ? $category->getTitle() : '',
            'url' => $this->urlFactory->create($product, $context),
        ];
    }

    private function isInStock(Product $product, McpContext $context): bool
    {
        $range = $this->priceRangeResolver->resolve($product, $context->request);
        if ($range !== null) {
            return $range->isAnyInStock();
        }

        return $product->isUnlimitedStock() || $product->getInStock() > 0;
    }
}
