<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Mcp\Catalog\ProductDetailMapper;
use GoldeneZeiten\Products\Mcp\Catalog\ProductFinder;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Describes one product in full, including its variants.
 */
final readonly class GetProductTool implements McpToolInterface
{
    public function __construct(
        private ProductFinder $productFinder,
        private ProductDetailMapper $detailMapper
    ) {}

    public function getName(): string
    {
        return 'get_product';
    }

    public function getDescription(): string
    {
        return 'Returns one product with its description, price, variants (with attributes, price and stock), '
            . 'manufacturer, categories and the address of its detail page. A product that does not exist or is '
            . 'not visible is an error.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'uid' => ['type' => 'integer', 'description' => 'Uid of the product, as returned by search_products.'],
            ],
            'required' => ['uid'],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $product = $this->productFinder->require((int)($arguments['uid'] ?? 0), $context);

        return $this->detailMapper->map($product, $context);
    }
}
