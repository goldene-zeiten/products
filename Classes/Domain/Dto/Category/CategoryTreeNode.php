<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Category;

use GoldeneZeiten\Products\Domain\Model\Category;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class CategoryTreeNode
{
    /**
     * @param CategoryTreeNode[] $children
     */
    public function __construct(
        private Category $category,
        private array $children,
        private int $depth,
        private string $slugPath
    ) {}

    public function getCategory(): Category
    {
        return $this->category;
    }

    /**
     * @return CategoryTreeNode[]
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    /**
     * Nested slug path from the tree root down to and including this node
     * (e.g. "main-category-1/sub-category-5/last-category-3"), precomputed once while the tree
     * is built rather than re-walking ancestors per node.
     */
    public function getSlugPath(): string
    {
        return $this->slugPath;
    }
}
