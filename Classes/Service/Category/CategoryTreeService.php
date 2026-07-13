<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Category;

use GoldeneZeiten\Products\Domain\Dto\Category\CategoryTreeNode;
use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Event\ModifyCategoryTreeEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

final class CategoryTreeService
{
    public function __construct(
        private readonly CategoryRepository $categoryRepository,
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    /**
     * @return CategoryTreeNode[]
     */
    public function getTree(): array
    {
        $tree = $this->buildLevel(0, $this->groupByParentUid(), 0, '', null);
        $event = new ModifyCategoryTreeEvent($tree);
        $this->eventDispatcher->dispatch($event);
        return $event->getTree();
    }

    /**
     * Tree rooted at $entryPointCategoryUid (inclusive), $maxLevels deep beneath it.
     * Returns an empty array when the entry point category doesn't exist.
     *
     * @return CategoryTreeNode[]
     */
    public function getSubtree(int $entryPointCategoryUid, int $maxLevels = PHP_INT_MAX): array
    {
        $entryPointCategory = $this->categoryRepository->findByUidIgnoringStoragePage($entryPointCategoryUid);
        if ($entryPointCategory === null) {
            return [];
        }
        $slugPath = $this->resolveSlugPath($entryPointCategory);
        $remainingLevels = $maxLevels === PHP_INT_MAX ? null : $maxLevels;
        return [new CategoryTreeNode(
            $entryPointCategory,
            $this->buildLevel($entryPointCategoryUid, $this->groupByParentUid(), 1, $slugPath, $remainingLevels),
            0,
            $slugPath
        )];
    }

    /**
     * Uid of the given category plus all descendant uids (flattened).
     *
     * @return int[]
     */
    public function getSubtreeUids(int $categoryUid): array
    {
        $nodes = $this->getSubtree($categoryUid);
        if ($nodes === []) {
            return [];
        }
        $uids = [];
        $this->flattenNodeUids($nodes[0], $uids);
        return $uids;
    }

    /**
     * @param int[] $uids
     */
    private function flattenNodeUids(CategoryTreeNode $node, array &$uids): void
    {
        $category = $node->getCategory();
        if ($category->getUid() !== null) {
            $uids[] = $category->getUid();
        }
        foreach ($node->getChildren() as $child) {
            $this->flattenNodeUids($child, $uids);
        }
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
     * Compose nested slug path from ancestor and optional product segments.
     */
    public function resolveSlugPath(Category $category, ?Product $product = null): string
    {
        $segments = array_map(
            fn(Category $ancestor): string => $this->ownSlugSegment($ancestor->getSlug()),
            array_filter(
                $this->getAncestorChain($category),
                fn(Category $ancestor): bool => !$ancestor->isHideInSlugPath()
            )
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
    private function buildLevel(int $parentUid, array $groupedByParentUid, int $depth, string $parentSlugPath, ?int $remainingLevels): array
    {
        if ($remainingLevels !== null && $remainingLevels <= 0) {
            return [];
        }
        $nextRemainingLevels = $remainingLevels === null ? null : $remainingLevels - 1;
        $nodes = [];
        foreach ($groupedByParentUid[$parentUid] ?? [] as $category) {
            $slugPath = $category->isHideInSlugPath()
                ? $parentSlugPath
                : $this->appendSegment($parentSlugPath, $this->ownSlugSegment($category->getSlug()));
            $nodes[] = new CategoryTreeNode(
                $category,
                $this->buildLevel((int)$category->getUid(), $groupedByParentUid, $depth + 1, $slugPath, $nextRemainingLevels),
                $depth,
                $slugPath
            );
        }
        return $nodes;
    }

    private function appendSegment(string $path, string $segment): string
    {
        return $path === '' ? $segment : $path . '/' . $segment;
    }

    public function ownSlugSegment(string $storedSlug): string
    {
        $segments = explode('/', trim($storedSlug, '/'));
        return (string)end($segments);
    }
}
