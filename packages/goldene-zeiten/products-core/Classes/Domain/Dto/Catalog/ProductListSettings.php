<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * How much of a list one page shows and whether the further pages are linked, as set on the content
 * element. An items-per-page value of 0 shows every product on one page.
 */
#[Exclude]
final readonly class ProductListSettings
{
    public function __construct(
        public int $itemsPerPage = 0,
        public bool $pagination = true,
        public string $sorting = ProductListCriteria::SORTING_MANUAL,
    ) {}

    /**
     * @param array<string, mixed> $contentElement the tt_content row of the list
     */
    public static function fromContentElement(array $contentElement): self
    {
        $sorting = (string)($contentElement['tx_products_sorting'] ?? '');
        return new self(
            max(0, (int)($contentElement['tx_products_items_per_page'] ?? 0)),
            (bool)($contentElement['tx_products_pagination'] ?? true),
            $sorting !== '' ? $sorting : ProductListCriteria::SORTING_MANUAL,
        );
    }

    public function isPaged(): bool
    {
        return $this->itemsPerPage > 0;
    }
}
