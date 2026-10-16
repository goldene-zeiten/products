<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Search\Domain\Dto;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Core\Pagination\PaginationInterface;
use TYPO3\CMS\Core\Pagination\PaginatorInterface;

/**
 * One page of a product search. Without a term nothing was searched and there is no paginator.
 */
#[Exclude]
final readonly class SearchResult
{
    public function __construct(
        private string $term,
        private ?PaginatorInterface $paginator,
        private ?PaginationInterface $pagination,
        private int $totalCount = 0,
    ) {}

    /**
     * @return Product[]
     */
    public function getProducts(): array
    {
        if ($this->paginator === null) {
            return [];
        }
        $products = [];
        foreach ($this->paginator->getPaginatedItems() as $product) {
            $products[] = $product;
        }
        return $products;
    }

    public function getTerm(): string
    {
        return $this->term;
    }

    public function getCurrentPage(): int
    {
        return $this->paginator?->getCurrentPageNumber() ?? 1;
    }

    public function getTotalPages(): int
    {
        return $this->paginator?->getNumberOfPages() ?? 0;
    }

    public function getTotalCount(): int
    {
        return $this->totalCount;
    }

    public function getPaginator(): ?PaginatorInterface
    {
        return $this->paginator;
    }

    public function getPagination(): ?PaginationInterface
    {
        return $this->pagination;
    }

    public function hasSearched(): bool
    {
        return $this->term !== '';
    }

    public function hasResults(): bool
    {
        return $this->getTotalCount() > 0;
    }
}
