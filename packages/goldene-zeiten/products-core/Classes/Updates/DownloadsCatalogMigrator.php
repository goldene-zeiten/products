<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use Doctrine\DBAL\ParameterType;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Migrates the legacy downloads catalog (MM-linked FAL) into Product::downloads.
 * The legacy tt_products_products_mm_downloads table links products to tt_products_downloads rows,
 * each of which has a FAL file_uid field pointing to sys_file_reference rows. This migrator
 * copies those existing references into the new product's downloads field.
 */
final class DownloadsCatalogMigrator
{
    private const MM_TABLE = 'tt_products_products_mm_downloads';
    private const DOWNLOADS_TABLE = 'tt_products_downloads';
    private const LEGACY_DOWNLOADS_FIELD = 'file_uid';
    private const LOCAL_TABLE = 'tx_products_domain_model_product';
    private const LOCAL_FIELD = 'downloads';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper
    ) {}

    /**
     * Migrate all pending MM rows, creating new sys_file_reference rows in the local table.
     */
    public function migrateAll(OutputInterface $output): void
    {
        $attemptedMmUids = [];
        while (($rows = $this->fetchPendingMmRows()) !== []) {
            $newRows = array_filter(
                $rows,
                fn(array $row): bool => !in_array((int)$row['mm_uid'], $attemptedMmUids, true)
            );
            if ($newRows === []) {
                break;
            }
            foreach ($newRows as $row) {
                $attemptedMmUids[] = (int)$row['mm_uid'];
                $this->migrateRow((int)$row['mm_uid'], (int)$row['uid_local'], (int)$row['uid_foreign'], $output);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchPendingMmRows(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select(
            'mm.uid AS mm_uid',
            'mm.uid_local',
            'mm.uid_foreign'
        )
            ->from(self::MM_TABLE, 'mm')
            ->andWhere($queryBuilder->expr()->eq('mm.deleted', 0))
            ->andWhere($this->notYetMigratedCondition($queryBuilder))
            ->setMaxResults(LegacyMigrationHelper::BATCH_SIZE);
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    private function notYetMigratedCondition(QueryBuilder $queryBuilder): string
    {
        return sprintf(
            'NOT EXISTS (SELECT 1 FROM %s WHERE tablenames = %s AND fieldname = %s '
                . 'AND uid_foreign = %s AND deleted = 0 LIMIT 1)',
            $queryBuilder->quoteIdentifier('sys_file_reference'),
            $queryBuilder->createNamedParameter(self::LOCAL_TABLE),
            $queryBuilder->createNamedParameter(self::LOCAL_FIELD),
            $queryBuilder->quoteIdentifier('mm.uid_local')
        );
    }

    private function migrateRow(
        int $mmUid,
        int $legacyProductUid,
        int $legacyDownloadUid,
        OutputInterface $output
    ): void {
        $localProductUid = $this->migrationHelper->resolveLocalUid(
            'tt_products',
            $legacyProductUid,
            'tx_products_domain_model_product'
        );
        if ($localProductUid === null || $localProductUid === 0) {
            $output->writeln(sprintf(
                '<comment>tt_products_products_mm_downloads uid %d: product uid %d not found in migration map, skipped.</comment>',
                $mmUid,
                $legacyProductUid
            ));
            return;
        }

        $legacyReferences = $this->fetchLegacyReferences($legacyDownloadUid);
        foreach ($legacyReferences as $reference) {
            $this->copyReferenceIfNotExists(
                (int)$reference['uid_local'],
                $localProductUid,
                (int)$reference['pid']
            );
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLegacyReferences(int $legacyDownloadUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')
            ->from('sys_file_reference')
            ->andWhere($queryBuilder->expr()->eq(
                'tablenames',
                $queryBuilder->createNamedParameter(self::DOWNLOADS_TABLE)
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'fieldname',
                $queryBuilder->createNamedParameter(self::LEGACY_DOWNLOADS_FIELD)
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'uid_foreign',
                $queryBuilder->createNamedParameter($legacyDownloadUid, ParameterType::INTEGER)
            ))
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->orderBy('sorting_foreign')
            ->executeQuery()->fetchAllAssociative();
    }

    private function copyReferenceIfNotExists(int $fileUid, int $localProductUid, int $pid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $exists = $queryBuilder->select('uid')
            ->from('sys_file_reference')
            ->andWhere($queryBuilder->expr()->eq(
                'tablenames',
                $queryBuilder->createNamedParameter(self::LOCAL_TABLE)
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'fieldname',
                $queryBuilder->createNamedParameter(self::LOCAL_FIELD)
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'uid_foreign',
                $queryBuilder->createNamedParameter($localProductUid, ParameterType::INTEGER)
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'uid_local',
                $queryBuilder->createNamedParameter($fileUid, ParameterType::INTEGER)
            ))
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->setMaxResults(1)
            ->executeQuery()->fetchOne();

        if ($exists) {
            return;
        }

        $nextSortingForeign = $this->getNextSortingForeign($localProductUid);
        $insertQueryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $insertQueryBuilder->insert('sys_file_reference')->values([
            'pid' => $pid,
            'uid_local' => $fileUid,
            'uid_foreign' => $localProductUid,
            'tablenames' => self::LOCAL_TABLE,
            'fieldname' => self::LOCAL_FIELD,
            'sorting_foreign' => $nextSortingForeign,
        ])->executeStatement();
    }

    private function getNextSortingForeign(int $localProductUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder
            ->selectLiteral('MAX(' . $queryBuilder->quoteIdentifier('sorting_foreign') . ') AS max_sorting')
            ->from('sys_file_reference')
            ->andWhere($queryBuilder->expr()->eq(
                'tablenames',
                $queryBuilder->createNamedParameter(self::LOCAL_TABLE)
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'fieldname',
                $queryBuilder->createNamedParameter(self::LOCAL_FIELD)
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'uid_foreign',
                $queryBuilder->createNamedParameter($localProductUid, ParameterType::INTEGER)
            ))
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->executeQuery()->fetchOne();

        $maxSorting = ($result === false || $result === null) ? -1 : (int)$result;
        return $maxSorting + 1;
    }
}
