<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Backend;

/**
 * Checks whether a category or product is visible to a backend user given their
 * resolved category mounts (see CategoryMountResolver). Shared between the tree AJAX
 * endpoint and the module controller so both enforce identical access rules.
 */
final class CategoryAccessGuard
{
    public function __construct(private readonly CategoryTreeRepository $treeRepository) {}

    /**
     * @param int[]|null $mounts null means unrestricted (admin)
     */
    public function isCategoryAccessible(int $categoryUid, ?array $mounts): bool
    {
        if ($mounts === null) {
            return true;
        }
        return $this->isCategoryOrAncestorInMounts($categoryUid, $mounts);
    }

    /**
     * @param int[]|null $mounts null means unrestricted (admin)
     */
    public function isProductAccessible(int $productUid, ?array $mounts): bool
    {
        if ($mounts === null) {
            return true;
        }
        foreach ($this->treeRepository->fetchCategoryUidsOfProduct($productUid) as $categoryUid) {
            if ($this->isCategoryOrAncestorInMounts($categoryUid, $mounts)) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param int[] $mounts
     */
    private function isCategoryOrAncestorInMounts(int $categoryUid, array $mounts): bool
    {
        $current = $categoryUid;
        for ($depth = 0; $depth < 100 && $current > 0; $depth++) {
            if (in_array($current, $mounts, true)) {
                return true;
            }
            $current = $this->treeRepository->fetchParentCategoryUid($current) ?? 0;
        }
        return false;
    }
}
