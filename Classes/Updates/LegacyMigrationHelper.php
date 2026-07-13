<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Tracks migrated rows via `tx_products_migration_map` to prevent duplication on repeated runs.
 */
final class LegacyMigrationHelper
{
    public const BATCH_SIZE = 500;

    private const MAP_TABLE = 'tx_products_migration_map';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    public function tablesExist(string ...$legacyTables): bool
    {
        foreach ($legacyTables as $legacyTable) {
            $schemaManager = $this->connectionPool->getConnectionForTable($legacyTable)->createSchemaManager();
            if (!$schemaManager->tablesExist([$legacyTable])) {
                return false;
            }
        }
        return true;
    }

    public function countUnmigrated(string $legacyTable, string $localTable, bool $legacyTableHasDeletedColumn = true): int
    {
        $queryBuilder = $this->unmigratedQueryBuilder($legacyTable, $localTable, $legacyTableHasDeletedColumn);
        return (int)$queryBuilder->count('legacy.uid')->executeQuery()->fetchOne();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchUnmigratedBatch(string $legacyTable, string $localTable, bool $legacyTableHasDeletedColumn = true): array
    {
        $queryBuilder = $this->unmigratedQueryBuilder($legacyTable, $localTable, $legacyTableHasDeletedColumn);
        return $queryBuilder->select('legacy.*')
            ->orderBy('legacy.uid')
            ->setMaxResults(self::BATCH_SIZE)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    public function recordMapping(string $legacyTable, int $legacyUid, string $localTable, int $localUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::MAP_TABLE);
        $queryBuilder->insert(self::MAP_TABLE)->values([
            'legacy_table' => $legacyTable,
            'legacy_uid' => $legacyUid,
            'local_table' => $localTable,
            'local_uid' => $localUid,
        ])->executeStatement();
    }

    public function resolveLocalUid(string $legacyTable, int $legacyUid, string $localTable): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::MAP_TABLE);
        $localUid = $queryBuilder->select('local_uid')
            ->from(self::MAP_TABLE)
            ->where(
                $queryBuilder->expr()->eq('legacy_table', $queryBuilder->createNamedParameter($legacyTable)),
                $queryBuilder->expr()->eq(
                    'legacy_uid',
                    $queryBuilder->createNamedParameter($legacyUid, ParameterType::INTEGER)
                ),
                $queryBuilder->expr()->eq('local_table', $queryBuilder->createNamedParameter($localTable))
            )
            ->executeQuery()
            ->fetchOne();
        return $localUid === false ? null : (int)$localUid;
    }

    /**
     * Finds unmigrated legacy rows via NOT EXISTS subquery.
     */
    private function unmigratedQueryBuilder(string $legacyTable, string $localTable, bool $legacyTableHasDeletedColumn = true): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($legacyTable);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->from($legacyTable, 'legacy');
        if ($legacyTableHasDeletedColumn) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('legacy.deleted', 0));
        }
        $queryBuilder->andWhere(sprintf(
            'NOT EXISTS (SELECT 1 FROM %s WHERE legacy_table = %s AND local_table = %s AND legacy_uid = %s)',
            $queryBuilder->quoteIdentifier(self::MAP_TABLE),
            $queryBuilder->createNamedParameter($legacyTable),
            $queryBuilder->createNamedParameter($localTable),
            $queryBuilder->quoteIdentifier('legacy.uid')
        ));
        return $queryBuilder;
    }
}
