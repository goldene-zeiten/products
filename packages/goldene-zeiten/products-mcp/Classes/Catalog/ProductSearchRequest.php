<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * What an assistant searches for: a term, optional filters and the page wanted. Prices are decimal strings
 * in the pricing mode of the shop.
 */
#[Exclude]
final readonly class ProductSearchRequest
{
    public const MAX_PER_PAGE = 50;

    public function __construct(
        public string $term = '',
        public ?int $categoryUid = null,
        public ?int $manufacturerUid = null,
        public ?string $minPrice = null,
        public ?string $maxPrice = null,
        public int $page = 1,
        public int $perPage = 10
    ) {}
}
