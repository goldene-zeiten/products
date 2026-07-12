<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Backend;

use Doctrine\DBAL\ArrayParameterType;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Resolves effective `tx_products_category_mounts` from user and group fields.
 */
final class CategoryMountResolver
{
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return int[]|null null means unrestricted (admin user)
     */
    public function resolveMountUids(BackendUserAuthentication $backendUser): ?array
    {
        if ($backendUser->isAdmin()) {
            return null;
        }

        $mounts = GeneralUtility::intExplode(',', (string)($backendUser->user['tx_products_category_mounts'] ?? ''), true);
        foreach ($this->fetchGroupMounts($backendUser->userGroupsUID) as $groupMounts) {
            $mounts = [...$mounts, ...GeneralUtility::intExplode(',', $groupMounts, true)];
        }

        return array_values(array_unique($mounts));
    }

    /**
     * @param int[] $groupUids
     * @return string[]
     */
    private function fetchGroupMounts(array $groupUids): array
    {
        if ($groupUids === []) {
            return [];
        }

        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('be_groups');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder->select('tx_products_category_mounts')
            ->from('be_groups')
            ->where($queryBuilder->expr()->in(
                'uid',
                $queryBuilder->createNamedParameter($groupUids, ArrayParameterType::INTEGER)
            ))
            ->executeQuery()
            ->fetchFirstColumn();
    }
}
