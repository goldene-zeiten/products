<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Backend;

use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Per-category edit/delete permission checks using core Permission bitmask.
 */
final class CategoryPermissionGuard
{
    public function __construct(private readonly CategoryTreeRepository $treeRepository) {}

    public function isCategoryEditable(int $categoryUid, BackendUserAuthentication $backendUser): bool
    {
        if ($backendUser->isAdmin()) {
            return true;
        }
        $row = $this->treeRepository->fetchCategoryPermissionRow($categoryUid);
        if ($row === null) {
            return false;
        }
        return $this->calculatePermission($row, $backendUser)->editPagePermissionIsGranted();
    }

    public function isCategoryDeletable(int $categoryUid, BackendUserAuthentication $backendUser): bool
    {
        if ($backendUser->isAdmin()) {
            return true;
        }
        $row = $this->treeRepository->fetchCategoryPermissionRow($categoryUid);
        if ($row === null) {
            return false;
        }
        return $this->calculatePermission($row, $backendUser)->deletePagePermissionIsGranted();
    }

    /**
     * @param array{perms_userid: int, perms_user: int, perms_groupid: int, perms_group: int, perms_everybody: int} $row
     */
    private function calculatePermission(array $row, BackendUserAuthentication $backendUser): Permission
    {
        $permission = new Permission();
        if ((int)($backendUser->user['uid'] ?? 0) === $row['perms_userid']) {
            $permission->or(new Permission($row['perms_user']));
        }
        if ($row['perms_groupid'] > 0 && in_array($row['perms_groupid'], $backendUser->userGroupsUID, true)) {
            $permission->or(new Permission($row['perms_group']));
        }
        $permission->or(new Permission($row['perms_everybody']));
        return $permission;
    }
}
