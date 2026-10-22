<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use GoldeneZeiten\Products\Core\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;

/**
 * Resolves the category an assistant names to the categories a filter has to cover.
 */
final readonly class CategoryResolver
{
    public function __construct(
        private CategoryTreeService $categoryTreeService
    ) {}

    /**
     * The category and all categories beneath it.
     *
     * @return int[]
     * @throws ToolFailedException when there is no such category
     */
    public function subtreeUids(int $categoryUid): array
    {
        $uids = $this->categoryTreeService->getSubtreeUids($categoryUid);
        if ($uids === []) {
            throw new ToolFailedException(sprintf('There is no category with the uid %d.', $categoryUid), 1792710232);
        }

        return $uids;
    }
}
