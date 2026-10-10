<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use TYPO3\CMS\Backend\Utility\BackendUtility;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Type\Bitmask\Permission;

/**
 * Decides whether a backend user may see or change an order, the same way the page module would for
 * a record on the order's storage page: table permission, web mount and page permission. Admins may
 * do everything.
 */
final class OrderAccessGuard
{
    public function mayRead(int $orderPid, BackendUserAuthentication $backendUser): bool
    {
        if ($backendUser->isAdmin()) {
            return true;
        }
        return $backendUser->check('tables_select', 'tx_products_domain_model_order')
            && $this->hasPagePermission($orderPid, $backendUser, Permission::PAGE_SHOW);
    }

    public function mayWrite(int $orderPid, BackendUserAuthentication $backendUser): bool
    {
        if ($backendUser->isAdmin()) {
            return true;
        }
        return $backendUser->check('tables_modify', 'tx_products_domain_model_order')
            && $this->hasPagePermission($orderPid, $backendUser, Permission::CONTENT_EDIT);
    }

    private function hasPagePermission(int $pid, BackendUserAuthentication $backendUser, int $permission): bool
    {
        if ($pid <= 0) {
            return false;
        }
        $page = BackendUtility::getRecord('pages', $pid);
        return is_array($page) && $backendUser->doesUserHaveAccess($page, $permission);
    }
}
