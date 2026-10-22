<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tool;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Mcp\Catalog\ProductSearchRequest;
use GoldeneZeiten\Products\Mcp\Catalog\ProductSearchService;
use GoldeneZeiten\Products\Mcp\Catalog\ProductSummaryMapper;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Registry\McpToolInterface;

/**
 * Searches the products of the shop by term, category, manufacturer and price range, one page at a time.
 */
final readonly class SearchProductsTool implements McpToolInterface
{
    public function __construct(
        private ProductSearchService $searchService,
        private ProductSummaryMapper $summaryMapper
    ) {}

    public function getName(): string
    {
        return 'search_products';
    }

    public function getDescription(): string
    {
        return 'Searches the products by a term in title, item number and description, optionally limited to a '
            . 'category (with its subcategories), a manufacturer and a price range. Returns one page of products '
            . 'with price, availability, brand, category and the address of the detail page.';
    }

    public function getInputSchema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Search term; empty lists all products.'],
                'category' => ['type' => 'integer', 'description' => 'Uid of a category from list_categories.'],
                'manufacturer' => ['type' => 'integer', 'description' => 'Uid of a manufacturer from list_manufacturers.'],
                'minPrice' => ['type' => 'number', 'description' => 'Lowest price, in the price mode of the shop.'],
                'maxPrice' => ['type' => 'number', 'description' => 'Highest price, in the price mode of the shop.'],
                'page' => ['type' => 'integer', 'minimum' => 1, 'description' => 'Page number, starting at 1.'],
                'perPage' => [
                    'type' => 'integer',
                    'minimum' => 1,
                    'description' => sprintf('Products per page, at most %d.', ProductSearchRequest::MAX_PER_PAGE),
                ],
            ],
        ];
    }

    public function execute(array $arguments, McpContext $context): array
    {
        $request = $this->request($arguments);
        $found = $this->searchService->search($request, $context);

        return [
            'total' => $found['total'],
            'page' => $request->page,
            'perPage' => $request->perPage,
            'items' => array_map(
                fn(Product $product): array => $this->summaryMapper->map($product, $context),
                $found['products']
            ),
        ];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    private function request(array $arguments): ProductSearchRequest
    {
        return new ProductSearchRequest(
            trim((string)($arguments['query'] ?? '')),
            isset($arguments['category']) ? (int)$arguments['category'] : null,
            isset($arguments['manufacturer']) ? (int)$arguments['manufacturer'] : null,
            isset($arguments['minPrice']) ? $this->decimal($arguments['minPrice']) : null,
            isset($arguments['maxPrice']) ? $this->decimal($arguments['maxPrice']) : null,
            max(1, (int)($arguments['page'] ?? 1)),
            min(ProductSearchRequest::MAX_PER_PAGE, max(1, (int)($arguments['perPage'] ?? 10)))
        );
    }

    private function decimal(mixed $value): string
    {
        return Money::fromDecimalString((string)$value)->getDecimalString();
    }
}
