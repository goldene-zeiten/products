<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use GoldeneZeiten\Products\Backend\StorageFolderResolver;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\ConnectionPool;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Migrates `tt_products_cat` and overlays to `tx_products_domain_model_category`.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
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
        private readonly LegacyOverlayMigrator $overlayMigrator,
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
            || $this->overlayMigrator->hasPending($this->overlayConfig());
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
        $this->overlayMigrator->migrate($this->output, $this->overlayConfig(), $this->overlayValues(...));
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    private function overlayConfig(): OverlayMigrationConfig
    {
        return new OverlayMigrationConfig(
            legacyLanguageTable: self::LEGACY_LANGUAGE_TABLE,
            parentField: 'cat_uid',
            legacyParentTable: self::LEGACY_TABLE,
            localTable: self::LOCAL_TABLE,
        );
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
     * A single ascending-uid pass always migrates a parent before its children.
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

    /**
     * @param array<string, mixed> $winner
     * @return array<string, mixed>
     */
    private function overlayValues(array $winner): array
    {
        return [
            'title' => (string)$winner['title'],
            'slug' => (string)($winner['slug'] ?? ''),
            'description' => (string)($winner['note'] ?? ''),
            'parent_category' => 0,
        ];
    }
}
