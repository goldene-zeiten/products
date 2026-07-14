<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use GoldeneZeiten\Products\Updates\Prerequisites\ArticleMigrationPrerequisite;
use GoldeneZeiten\Products\Updates\Prerequisites\CategoryMigrationPrerequisite;
use GoldeneZeiten\Products\Updates\Prerequisites\ProductMigrationPrerequisite;
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
 * Migrates media fields to FAL. Requires {@see ProductMigrationPrerequisite},
 * {@see CategoryMigrationPrerequisite}, and {@see ArticleMigrationPrerequisite} to have run first.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
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
        private readonly DownloadsCatalogMigrator $downloadsCatalogMigrator,
        private readonly CategoryMigrationPrerequisite $categoryMigrationPrerequisite,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
        private readonly ArticleMigrationPrerequisite $articleMigrationPrerequisite,
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
        return 'Migrates product/category/article images, product datasheets, and downloads catalog '
            . 'to the new FAL fields. Secondary thumbnails (smallimage/sliderimage) are reported but not migrated.';
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
        if ($this->hasPendingDownloads()) {
            return true;
        }
        return false;
    }

    private function hasPendingDownloads(): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::DOWNLOADS_MM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $count = $queryBuilder->count('uid')->from(self::DOWNLOADS_MM_TABLE)
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->executeQuery()->fetchOne();
        return (int)$count > 0;
    }

    public function executeUpdate(): bool
    {
        if (!$this->migrationHelper->tablesExist(self::PRODUCT_TABLE)) {
            return true;
        }
        if (!$this->prerequisitesFulfilled()) {
            return false;
        }
        foreach ($this->mappings() as $mapping) {
            $this->migrateMapping($mapping);
        }
        $this->downloadsCatalogMigrator->migrateAll($this->output);
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [
            DatabaseUpdatedPrerequisite::class,
            CategoryMigrationPrerequisite::class,
            ProductMigrationPrerequisite::class,
            ArticleMigrationPrerequisite::class,
        ];
    }

    private function prerequisitesFulfilled(): bool
    {
        $fulfilled = true;
        if (!$this->categoryMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products_cat still has unmigrated rows; run the category migration '
                    . 'wizard (products_ttProductsCategoryMigration) first.</error>'
            );
            $fulfilled = false;
        }
        if (!$this->productMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products still has unmigrated rows; run the product migration '
                    . 'wizard (products_ttProductsProductMigration) first.</error>'
            );
            $fulfilled = false;
        }
        if (!$this->articleMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products_articles still has unmigrated rows; run the article migration '
                    . 'wizard (products_ttProductsArticleMigration) first.</error>'
            );
            $fulfilled = false;
        }
        return $fulfilled;
    }

    /**
     * @return LegacyMediaFieldMapping[]
     */
    public function mappings(): array
    {
        return [
            new LegacyMediaFieldMapping(self::PRODUCT_TABLE, 'image', 'tx_products_domain_model_product', 'images'),
            new LegacyMediaFieldMapping(self::PRODUCT_TABLE, 'datasheet', 'tx_products_domain_model_product', 'downloads'),
            new LegacyMediaFieldMapping(self::CATEGORY_TABLE, 'image', 'tx_products_domain_model_category', 'image'),
            new LegacyMediaFieldMapping(self::ARTICLE_TABLE, 'image', 'tx_products_domain_model_article', 'images'),
        ];
    }

    /**
     * A permanently-missing file never clears "pending", so the loop terminates on progress
     * (new legacy uids seen), not on the pending set becoming empty.
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
    }
}
