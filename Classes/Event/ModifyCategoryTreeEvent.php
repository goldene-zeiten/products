<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Dto\Category\CategoryTreeNode;
use GoldeneZeiten\Products\Service\Category\CategoryTreeService;

/**
 * Lets integrators reshape the built category tree - reorder branches, prune empty categories,
 * or inject virtual nodes for dynamic catalog navigation.
 * Mutable via {@see ModifyCategoryTreeEvent::setTree()}.
 *
 * @see CategoryTreeService::getTree()
 */
final class ModifyCategoryTreeEvent
{
    /**
     * @param CategoryTreeNode[] $tree
     */
    public function __construct(
        private array $tree
    ) {}

    /**
     * @return CategoryTreeNode[]
     */
    public function getTree(): array
    {
        return $this->tree;
    }

    /**
     * @param CategoryTreeNode[] $tree
     */
    public function setTree(array $tree): void
    {
        $this->tree = $tree;
    }
}
