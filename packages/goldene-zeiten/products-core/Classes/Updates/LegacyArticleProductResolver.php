<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Lists the legacy products a `tt_products_articles` row belongs to. tt_products links an article
 * either through its own `uid_product` (article mode 0) or through `tt_products_products_mm_articles`
 * (article modes 1 and 2); both sources are read, `uid_product` first.
 */
final class LegacyArticleProductResolver
{
    private ?bool $mmTableExists = null;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
    ) {}

    /**
     * @return int[] legacy `tt_products` uids, without duplicates
     */
    public function resolve(int $legacyArticleUid, int $uidProduct): array
    {
        $productUids = $uidProduct > 0 ? [$uidProduct] : [];
        $this->mmTableExists ??= $this->migrationHelper->tablesExist('tt_products_products_mm_articles');
        if (!$this->mmTableExists) {
            return $productUids;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_products_products_mm_articles');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid_local')
            ->from('tt_products_products_mm_articles')
            ->where(
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($legacyArticleUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('uid_local', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->orderBy('uid_local')
            ->executeQuery();
        while ($row = $result->fetchAssociative()) {
            $productUids[] = (int)$row['uid_local'];
        }
        return array_values(array_unique($productUids));
    }
}
