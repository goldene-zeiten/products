<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Which products a list shows and in which order: the built-in list mode, an optional selection of
 * products and of categories (each already including its subcategories where wanted), and the sorting.
 * Only the "all" listing can be limited to the configured storage folder.
 */
#[Exclude]
final readonly class ProductListCriteria
{
    public const SORTING_MANUAL = 'manual';
    public const SORTING_TITLE = 'title';
    public const SORTING_PRICE_ASC = 'price_asc';
    public const SORTING_PRICE_DESC = 'price_desc';
    public const SORTING_NEWEST = 'newest';

    /**
     * @param int[] $productUids empty: no restriction
     * @param int[] $categoryUids empty: no restriction
     * @param int[][] $requiredCategoryGroups a product must be in at least one category of every group
     */
    public function __construct(
        public string $mode = 'all',
        public array $productUids = [],
        public array $categoryUids = [],
        public string $sorting = self::SORTING_MANUAL,
        public int $newItemDays = 7,
        public bool $respectStoragePage = true,
        public array $requiredCategoryGroups = [],
    ) {}
}
