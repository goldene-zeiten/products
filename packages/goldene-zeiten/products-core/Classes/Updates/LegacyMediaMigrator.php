<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;

/**
 * Migrates legacy FAL-ish fields (filename list + usage counter) to local records. Uses presence
 * of sys_file_reference rows for idempotency (not migration_map, which is already claimed by
 * entity wizards).
 */
final class LegacyMediaMigrator
{
    private const ORIGINAL_UPLOADS_DIRECTORY = 'uploads/pics/';
    private const TARGET_FOLDER_IDENTIFIER = 'user_upload/products/';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly StorageRepository $storageRepository,
    ) {}

    /**
     * @param array<string, mixed> $legacyRow
     */
    public function migrateRow(MediaMigrationContext $context, array $legacyRow): void
    {
        if ($this->copyExistingReferences($context) > 0) {
            return;
        }
        $rawFilenames = trim((string)($legacyRow[$context->mapping->legacyField] ?? ''));
        if ($rawFilenames !== '') {
            $this->importRawFilenames($context, $rawFilenames);
        }
    }

    /**
     * Rows that carry legacy media (a filename list or legacy FAL references) and have no migrated
     * reference yet, in uid order after the given cursor. A row whose files are all missing on disk
     * stays pending, so callers page through with the cursor rather than until the set is empty.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchPendingRows(LegacyMediaFieldMapping $mapping, int $afterLegacyUid = 0): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($mapping->legacyTable);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder->select('legacy.*', 'map.local_uid AS media_migration_local_uid')
            ->from($mapping->legacyTable, 'legacy')
            ->join('legacy', 'tx_products_migration_map', 'map', (string)$queryBuilder->expr()->and(
                $queryBuilder->expr()->eq('map.legacy_uid', $queryBuilder->quoteIdentifier('legacy.uid')),
                $queryBuilder->expr()->eq('map.legacy_table', $queryBuilder->createNamedParameter($mapping->legacyTable)),
                $queryBuilder->expr()->eq('map.local_table', $queryBuilder->createNamedParameter($mapping->localTable))
            ))
            ->andWhere($queryBuilder->expr()->eq('legacy.deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->gt('map.local_uid', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->andWhere($queryBuilder->expr()->gt('legacy.uid', $queryBuilder->createNamedParameter($afterLegacyUid, Connection::PARAM_INT)))
            ->andWhere($this->hasLegacyMediaCondition($queryBuilder, $mapping))
            ->andWhere($this->notYetMigratedCondition($queryBuilder, $mapping))
            ->orderBy('legacy.uid')
            ->setMaxResults(LegacyMigrationHelper::BATCH_SIZE);
        return $queryBuilder->executeQuery()->fetchAllAssociative();
    }

    /**
     * tt_products 3.x dropped some filename columns in favour of FAL (`tt_products.datasheet`,
     * `tt_products_cat.image`); a filename list is only looked for where the legacy schema still has it.
     */
    private function hasLegacyMediaCondition(QueryBuilder $queryBuilder, LegacyMediaFieldMapping $mapping): string
    {
        $legacyReferences = sprintf(
            'EXISTS (SELECT 1 FROM %s WHERE tablenames = %s AND fieldname = %s AND uid_foreign = %s AND deleted = 0)',
            $queryBuilder->quoteIdentifier('sys_file_reference'),
            $queryBuilder->createNamedParameter($mapping->legacyTable),
            $queryBuilder->createNamedParameter($mapping->legacyField . '_uid'),
            $queryBuilder->quoteIdentifier('legacy.uid')
        );
        if (!$this->hasLegacyColumn($mapping->legacyTable, $mapping->legacyField)) {
            return $legacyReferences;
        }
        return (string)$queryBuilder->expr()->or(
            $queryBuilder->expr()->neq('legacy.' . $mapping->legacyField, $queryBuilder->createNamedParameter('')),
            $legacyReferences
        );
    }

    private function hasLegacyColumn(string $table, string $column): bool
    {
        return $this->connectionPool->getConnectionForTable($table)->createSchemaManager()->introspectTable($table)->hasColumn($column);
    }

    private function notYetMigratedCondition(QueryBuilder $queryBuilder, LegacyMediaFieldMapping $mapping): string
    {
        return sprintf(
            'NOT EXISTS (SELECT 1 FROM %s WHERE tablenames = %s AND fieldname = %s '
                . 'AND uid_foreign = %s AND deleted = 0)',
            $queryBuilder->quoteIdentifier('sys_file_reference'),
            $queryBuilder->createNamedParameter($mapping->localTable),
            $queryBuilder->createNamedParameter($mapping->localField),
            $queryBuilder->quoteIdentifier('map.local_uid')
        );
    }

    private function copyExistingReferences(MediaMigrationContext $context): int
    {
        $references = $this->fetchLegacyReferences($context);
        foreach ($references as $reference) {
            $this->insertReference(
                $context,
                (int)$reference['uid_local'],
                (int)$reference['sorting_foreign'],
                (int)$reference['pid']
            );
        }
        return count($references);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLegacyReferences(MediaMigrationContext $context): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from('sys_file_reference')
            ->andWhere($queryBuilder->expr()->eq(
                'tablenames',
                $queryBuilder->createNamedParameter($context->mapping->legacyTable)
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'fieldname',
                $queryBuilder->createNamedParameter($context->mapping->legacyField . '_uid')
            ))
            ->andWhere($queryBuilder->expr()->eq(
                'uid_foreign',
                $queryBuilder->createNamedParameter($context->legacyUid, ParameterType::INTEGER)
            ))
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->orderBy('sorting_foreign')
            ->executeQuery()->fetchAllAssociative();
    }

    private function insertReference(MediaMigrationContext $context, int $fileUid, int $sortingForeign, int $pid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->insert('sys_file_reference')->values([
            'pid' => $pid,
            'uid_local' => $fileUid,
            'uid_foreign' => $context->localUid,
            'tablenames' => $context->mapping->localTable,
            'fieldname' => $context->mapping->localField,
            'sorting_foreign' => $sortingForeign,
        ])->executeStatement();
    }

    private function importRawFilenames(MediaMigrationContext $context, string $rawFilenames): void
    {
        $storage = $this->storageRepository->getDefaultStorage();
        if ($storage === null) {
            $context->output->writeln('<error>No default file storage configured, media import skipped.</error>');
            return;
        }
        $targetFolder = $this->resolveTargetFolder($storage);
        foreach (explode(',', $rawFilenames) as $sorting => $filename) {
            $this->importSingleFile($context, trim((string)$filename), (int)$sorting, $targetFolder);
        }
    }

    private function resolveTargetFolder(ResourceStorage $storage): Folder
    {
        return $storage->hasFolder(self::TARGET_FOLDER_IDENTIFIER)
            ? $storage->getFolder(self::TARGET_FOLDER_IDENTIFIER)
            : $storage->createFolder(self::TARGET_FOLDER_IDENTIFIER);
    }

    private function importSingleFile(MediaMigrationContext $context, string $filename, int $sorting, Folder $targetFolder): void
    {
        if ($filename === '') {
            return;
        }
        $sourcePath = Environment::getPublicPath() . '/' . self::ORIGINAL_UPLOADS_DIRECTORY . $filename;
        if (!file_exists($sourcePath)) {
            $context->output->writeln(sprintf(
                '<comment>%s uid %d: media file "%s" not found on disk, skipped.</comment>',
                $context->mapping->legacyTable,
                $context->legacyUid,
                $filename
            ));
            return;
        }
        $file = $this->importOrReuseFile($sourcePath, $filename, $targetFolder);
        $this->insertReference($context, $file->getUid(), $sorting, 0);
    }

    /**
     * The legacy file is copied, never moved: several legacy records may list the same file, and
     * each of them must find it. A file already imported with identical content is reused.
     */
    private function importOrReuseFile(string $sourcePath, string $filename, Folder $targetFolder): File
    {
        if ($targetFolder->hasFile($filename)) {
            $existingFile = $targetFolder->getStorage()->getFileInFolder($filename, $targetFolder);
            if ($existingFile instanceof File && $existingFile->getSha1() === sha1_file($sourcePath)) {
                return $existingFile;
            }
        }
        return $targetFolder->getStorage()->addFile(
            $sourcePath,
            $targetFolder,
            $filename,
            DuplicationBehavior::RENAME,
            false
        );
    }
}
