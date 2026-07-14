<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Search;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class SearchResult
{
    /**
     * @param Product[] $products
     */
    public function __construct(
        private array $products,
        private string $term,
        private int $currentPage,
        private int $totalPages,
        private int $totalCount
    ) {}

    /**
     * @return Product[]
     */
    public function getProducts(): array
    {
        return $this->products;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    public function getTotalPages(): int
    {
        return $this->totalPages;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function hasSearched(): bool
    {
        return $this->term !== '';
    }

    public function hasResults(): bool
    {
        return $this->totalCount > 0;
    }
}
