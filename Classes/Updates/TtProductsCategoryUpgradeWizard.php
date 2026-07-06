<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Backend\StorageFolderResolver;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
// EXT:install namespaces are valid through TYPO3 v14 (deprecated there); migrate to the
// TYPO3\CMS\Core\Attribute\UpgradeWizard / TYPO3\CMS\Core\Updates\* equivalents once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrates the legacy `tt_products_cat` tree and its `tt_products_cat_language` overlays
 * to `tx_products_domain_model_category`. Idempotent via `tx_products_migration_map`.
 */
#[UpgradeWizard('products_ttProductsCategoryMigration')]
final class TtProductsCategoryUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const LEGACY_TABLE = 'tt_products_cat';
    private const LEGACY_LANGUAGE_TABLE = 'tt_products_cat_language';
    private const LOCAL_TABLE = 'tx_products_domain_model_category';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyOverlayDeduplicator $overlayDeduplicator,
        private readonly StorageFolderResolver $storageFolderResolver,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate tt_products_cat to tx_products_domain_model_category';
    }

    public function getDescription(): string
    {
        return 'Migrates legacy product categories and their tt_products_cat_language overlays '
            . 'into the new category tree, re-linking parent relations along the way.';
    }

    public function updateNecessary(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::LEGACY_TABLE)) {
            return false;
        }
        return $this->migrationHelper->countUnmigrated(self::LEGACY_TABLE, self::LOCAL_TABLE) > 0
            || $this->overlaysToMigrate() !== [];
    }

    public function executeUpdate(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::LEGACY_TABLE)) {
            return true;
        }
        $pid = $this->storageFolderResolver->resolve();
        while (($rows = $this->migrationHelper->fetchUnmigratedBatch(self::LEGACY_TABLE, self::LOCAL_TABLE)) !== []) {
            foreach ($rows as $row) {
                $this->migrateCategory($row, $pid);
            }
        }
        $this->migrateOverlays();
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function migrateCategory(array $legacyRow, int $pid): void
    {
        $legacyUid = (int)$legacyRow['uid'];
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_TABLE);
        $queryBuilder->insert(self::LOCAL_TABLE)->values([
            'pid' => $pid,
            'hidden' => (int)$legacyRow['hidden'],
            'sys_language_uid' => 0,
            'title' => (string)$legacyRow['title'],
            'slug' => (string)($legacyRow['slug'] ?? ''),
            'description' => (string)($legacyRow['note'] ?? ''),
            'parent_category' => $this->resolveParent($legacyUid, (int)$legacyRow['parent_category']),
        ])->executeStatement();
        $localUid = (int)$this->connectionPool->getConnectionForTable(self::LOCAL_TABLE)->lastInsertId();
        $this->migrationHelper->recordMapping(self::LEGACY_TABLE, $legacyUid, self::LOCAL_TABLE, $localUid);
    }

    /**
     * Legacy categories can only reference an already-existing category as their parent, so a
     * single ascending-uid pass always migrates a parent before its children. A parent that still
     * can't be resolved is genuinely orphaned (0, or a deleted/removed legacy row).
     */
    private function resolveParent(int $legacyUid, int $legacyParentUid): int
    {
        if ($legacyParentUid === 0) {
            return 0;
        }
        $localUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, $legacyParentUid, self::LOCAL_TABLE);
        if ($localUid === null) {
            $this->output->writeln(sprintf(
                '<comment>tt_products_cat uid %d referenced missing parent uid %d, linked to root instead.</comment>',
                $legacyUid,
                $legacyParentUid
            ));
            return 0;
        }
        return $localUid;
    }

    private function migrateOverlays(): void
    {
        foreach ($this->overlaysToMigrate() as $winner) {
            $this->migrateOverlay($winner);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function overlaysToMigrate(): array
    {
        if (!$this->migrationHelper->tablesExist(self::LEGACY_LANGUAGE_TABLE)) {
            return [];
        }
        $deduplicated = $this->overlayDeduplicator->deduplicate($this->fetchLanguageRows(), 'cat_uid');
        $this->reportLosers($deduplicated['losers']);
        return array_values(array_filter(
            $deduplicated['winners'],
            fn(array $winner): bool => $this->migrationHelper->resolveLocalUid(
                self::LEGACY_LANGUAGE_TABLE,
                (int)$winner['uid'],
                self::LOCAL_TABLE
            ) === null
        ));
    }

    /**
     * @param array<int, array<string, mixed>> $losers
     */
    private function reportLosers(array $losers): void
    {
        foreach ($losers as $loser) {
            $this->output->writeln(sprintf(
                '<comment>Skipped duplicate tt_products_cat_language uid %d (parent %d, language %d).</comment>',
                (int)$loser['uid'],
                (int)$loser['cat_uid'],
                (int)$loser['sys_language_uid']
            ));
        }
    }

    /**
     * @param array<string, mixed> $winner
     */
    private function migrateOverlay(array $winner): void
    {
        $parentLocalUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, (int)$winner['cat_uid'], self::LOCAL_TABLE);
        if ($parentLocalUid === null) {
            $this->output->writeln(sprintf(
                '<comment>Skipped tt_products_cat_language uid %d: parent uid %d was never migrated.</comment>',
                (int)$winner['uid'],
                (int)$winner['cat_uid']
            ));
            // Recorded with a sentinel 0 local uid so this permanently unmigratable
            // overlay is not reconsidered (and re-warned about) on every future run.
            $this->migrationHelper->recordMapping(self::LEGACY_LANGUAGE_TABLE, (int)$winner['uid'], self::LOCAL_TABLE, 0);
            return;
        }
        $this->insertOverlay($winner, $parentLocalUid);
    }

    /**
     * @param array<string, mixed> $winner
     */
    private function insertOverlay(array $winner, int $parentLocalUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_TABLE);
        $queryBuilder->insert(self::LOCAL_TABLE)->values([
            'pid' => $this->fetchPid($parentLocalUid),
            'hidden' => (int)$winner['hidden'],
            'sys_language_uid' => (int)$winner['sys_language_uid'],
            'l10n_parent' => $parentLocalUid,
            'title' => (string)$winner['title'],
            'slug' => (string)($winner['slug'] ?? ''),
            'description' => (string)($winner['note'] ?? ''),
            'parent_category' => 0,
        ])->executeStatement();
        $localUid = (int)$this->connectionPool->getConnectionForTable(self::LOCAL_TABLE)->lastInsertId();
        $this->migrationHelper->recordMapping(self::LEGACY_LANGUAGE_TABLE, (int)$winner['uid'], self::LOCAL_TABLE, $localUid);
    }

    private function fetchPid(int $localUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $pid = $queryBuilder->select('pid')->from(self::LOCAL_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($localUid, ParameterType::INTEGER)))
            ->executeQuery()->fetchOne();
        return (int)$pid;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchLanguageRows(): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LEGACY_LANGUAGE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from(self::LEGACY_LANGUAGE_TABLE)->executeQuery()->fetchAllAssociative();
    }
}
