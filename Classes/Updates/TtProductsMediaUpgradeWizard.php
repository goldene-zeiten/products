<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use Doctrine\DBAL\ParameterType;
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
 * Migrates the `tt_products`/`tt_products_cat`/`tt_products_articles` image and datasheet fields
 * to the FAL fields added in Milestone 2. Requires the corresponding entity wizard
 * (category/product/article) to have already run, since it works off `tx_products_migration_map`.
 *
 * Out of scope, reported via a notice instead of migrated: `smallimage`/`sliderimage` (redundant
 * pre-generated thumbnails; FAL generates thumbnails on demand) and the separate
 * `tt_products_downloads` catalog entity linked via `tt_products_products_mm_downloads` (a
 * many-to-many "download library" concept this extension does not replicate; see the Milestone 2
 * plan). Operators are told to re-attach any such linked downloads to the product manually.
 */
#[UpgradeWizard('products_ttProductsMediaMigration')]
final class TtProductsMediaUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const PRODUCT_TABLE = 'tt_products';
    private const CATEGORY_TABLE = 'tt_products_cat';
    private const ARTICLE_TABLE = 'tt_products_articles';
    private const DOWNLOADS_MM_TABLE = 'tt_products_products_mm_downloads';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyMediaMigrator $mediaMigrator,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate tt_products images and datasheets to FAL';
    }

    public function getDescription(): string
    {
        return 'Migrates product/category/article images and product datasheets to the new FAL '
            . 'fields. Secondary thumbnails (smallimage/sliderimage) and the separate downloads '
            . 'catalog entity are reported, not migrated.';
    }

    public function updateNecessary(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::PRODUCT_TABLE)) {
            return false;
        }
        foreach ($this->mappings() as $mapping) {
            if ($this->mediaMigrator->fetchPendingRows($mapping) !== []) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::PRODUCT_TABLE)) {
            return true;
        }
        foreach ($this->mappings() as $mapping) {
            $this->migrateMapping($mapping);
        }
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
     * @return LegacyMediaFieldMapping[]
     */
    private function mappings(): array
    {
        return [
            new LegacyMediaFieldMapping(self::PRODUCT_TABLE, 'image', 'tx_products_domain_model_product', 'images'),
            new LegacyMediaFieldMapping(self::PRODUCT_TABLE, 'datasheet', 'tx_products_domain_model_product', 'downloads'),
            new LegacyMediaFieldMapping(self::CATEGORY_TABLE, 'image', 'tx_products_domain_model_category', 'image'),
            new LegacyMediaFieldMapping(self::ARTICLE_TABLE, 'image', 'tx_products_domain_model_article', 'images'),
        ];
    }

    /**
     * A row without a matching legacy sys_file_reference and without a resolvable on-disk file
     * (see LegacyMediaMigrator) never gains a local sys_file_reference, so it would stay "pending"
     * forever. Termination is therefore based on making progress (new legacy uids seen), not on
     * the pending set becoming empty.
     */
    private function migrateMapping(LegacyMediaFieldMapping $mapping): void
    {
        $attemptedLegacyUids = [];
        while (($rows = $this->mediaMigrator->fetchPendingRows($mapping)) !== []) {
            $newRows = array_filter(
                $rows,
                fn(array $row): bool => !in_array((int)$row['uid'], $attemptedLegacyUids, true)
            );
            if ($newRows === []) {
                break;
            }
            foreach ($newRows as $row) {
                $attemptedLegacyUids[] = (int)$row['uid'];
                $this->migrateRow($mapping, $row);
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function migrateRow(LegacyMediaFieldMapping $mapping, array $row): void
    {
        $localUid = (int)$row['media_migration_local_uid'];
        unset($row['media_migration_local_uid']);
        $this->mediaMigrator->migrateRow(
            new MediaMigrationContext($this->output, $mapping, (int)$row['uid'], $localUid),
            $row
        );
        $this->reportSkippedSecondaryMedia($mapping->legacyTable, $row);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function reportSkippedSecondaryMedia(string $legacyTable, array $row): void
    {
        $secondaryField = $legacyTable === self::CATEGORY_TABLE ? 'sliderimage' : 'smallimage';
        if (trim((string)($row[$secondaryField] ?? '')) !== '') {
            $this->output->writeln(sprintf(
                '<comment>%s uid %d: "%s" is a redundant thumbnail and was not migrated.</comment>',
                $legacyTable,
                (int)$row['uid'],
                $secondaryField
            ));
        }
        if ($legacyTable === self::PRODUCT_TABLE) {
            $this->reportLinkedDownloadsCatalog((int)$row['uid']);
        }
    }

    private function reportLinkedDownloadsCatalog(int $legacyProductUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::DOWNLOADS_MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $count = $queryBuilder->count('uid')->from(self::DOWNLOADS_MM_TABLE)
            ->andWhere($queryBuilder->expr()->eq(
                'uid_local',
                $queryBuilder->createNamedParameter($legacyProductUid, ParameterType::INTEGER)
            ))
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->executeQuery()->fetchOne();
        if ((int)$count > 0) {
            $this->output->writeln(sprintf(
                '<comment>tt_products uid %d had catalog downloads linked; the downloads catalog '
                    . 'is not migrated, re-attach them to the product manually if still needed.</comment>',
                $legacyProductUid
            ));
        }
    }
}
