<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Category;

use GoldeneZeiten\Products\Domain\Dto\Category\CategoryTreeNode;
use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\CategoryRepository;

/**
 * Definition layer for category browsing: the tree structure, ancestor chains and the nested
 * slug path (e.g. "main-category-1/sub-category-5/last-category-3") are all resolved here so
 * that navigation/listing controllers and the route enhancer share one source of truth instead
 * of each re-deriving category ancestry themselves.
 */
final class CategoryTreeService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository
    ) {}

    /**
     * @return CategoryTreeNode[]
     */
    public function getTree(): array
    {
        return $this->buildLevel(0, $this->groupByParentUid(), 0);
    }

    /**
     * Root-first, including $category itself.
     *
     * @return Category[]
     */
    public function getAncestorChain(Category $category): array
    {
        $chain = [$category];
        $parent = $category->getParentCategory();
        while ($parent instanceof Category) {
            array_unshift($chain, $parent);
            $parent = $parent->getParentCategory();
        }
        return $chain;
    }

    /**
     * Each ancestor's own slug segment (not its full, page-prefixed `slug` column value) joined
     * by "/", with the product's own segment appended when given - category ancestry has no
     * representation in the stored `slug` column at all (TCA's `prefixParentPageSlug` only
     * prefixes the containing *page's* slug), so the nested path is composed here instead.
     */
    public function resolveSlugPath(Category $category, ?Product $product = null): string
    {
        $segments = array_map(
            fn(Category $ancestor): string => $this->ownSlugSegment($ancestor->getSlug()),
            $this->getAncestorChain($category)
        );
        if ($product instanceof Product) {
            $segments[] = $this->ownSlugSegment($product->getSlug());
        }
        return implode('/', $segments);
    }

    /**
     * @return array<int, Category[]>
     */
    private function groupByParentUid(): array
    {
        $grouped = [];
        foreach ($this->categoryRepository->findAllIgnoringStoragePage() as $category) {
            $parentUid = $category->getParentCategory()?->getUid() ?? 0;
            $grouped[$parentUid][] = $category;
        }
        return $grouped;
    }

    /**
     * @param array<int, Category[]> $groupedByParentUid
     * @return CategoryTreeNode[]
     */
    private function buildLevel(int $parentUid, array $groupedByParentUid, int $depth): array
    {
        $nodes = [];
        foreach ($groupedByParentUid[$parentUid] ?? [] as $category) {
            $nodes[] = new CategoryTreeNode(
                $category,
                $this->buildLevel((int)$category->getUid(), $groupedByParentUid, $depth + 1),
                $depth
            );
        }
        return $nodes;
    }

    private function ownSlugSegment(string $storedSlug): string
    {
        $segments = explode('/', trim($storedSlug, '/'));
        return (string)end($segments);
    }
}
