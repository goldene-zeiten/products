<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use GoldeneZeiten\Products\Backend\StorageFolderResolver;
use GoldeneZeiten\Products\Updates\Prerequisites\CategoryMigrationPrerequisite;
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
 * Migrates `tt_products` and overlays to `tx_products_domain_model_product`. Requires
 * {@see CategoryMigrationPrerequisite} to have run first.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsProductMigration')]
final class TtProductsProductUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const LEGACY_TABLE = 'tt_products';
    private const LEGACY_LANGUAGE_TABLE = 'tt_products_language';
    private const LEGACY_CATEGORY_TABLE = 'tt_products_cat';
    private const LOCAL_TABLE = 'tx_products_domain_model_product';
    private const LOCAL_CATEGORY_TABLE = 'tx_products_domain_model_category';
    private const LOCAL_CATEGORY_MM_TABLE = 'tx_products_product_category_mm';
    private const LOCAL_TAXCLASS_TABLE = 'tx_products_domain_model_taxclass';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyOverlayMigrator $overlayMigrator,
        private readonly StorageFolderResolver $storageFolderResolver,
        private readonly CategoryMigrationPrerequisite $categoryMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate tt_products to tx_products_domain_model_product';
    }

    public function getDescription(): string
    {
        return 'Migrates legacy products, their category assignment, tax class and '
            . 'tt_products_language overlays. Product images are not migrated.';
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
        if (!$this->categoryMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products_cat still has unmigrated rows; run the category migration '
                    . 'wizard (products_ttProductsCategoryMigration) first.</error>'
            );
            return false;
        }
        $pid = $this->storageFolderResolver->resolve();
        while (($rows = $this->migrationHelper->fetchUnmigratedBatch(self::LEGACY_TABLE, self::LOCAL_TABLE)) !== []) {
            foreach ($rows as $row) {
                $this->migrateProduct($row, $pid);
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
        return [DatabaseUpdatedPrerequisite::class, CategoryMigrationPrerequisite::class];
    }

    private function overlayConfig(): OverlayMigrationConfig
    {
        return new OverlayMigrationConfig(
            legacyLanguageTable: self::LEGACY_LANGUAGE_TABLE,
            parentField: 'prod_uid',
            legacyParentTable: self::LEGACY_TABLE,
            localTable: self::LOCAL_TABLE,
        );
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function migrateProduct(array $legacyRow, int $pid): void
    {
        $legacyUid = (int)$legacyRow['uid'];
        $this->noticeIfImagePresent($legacyRow, $legacyUid);
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_TABLE);
        $queryBuilder->insert(self::LOCAL_TABLE)->values($this->productValues($legacyRow, $pid, $legacyUid))->executeStatement();
        $localUid = (int)$this->connectionPool->getConnectionForTable(self::LOCAL_TABLE)->lastInsertId();
        $this->migrationHelper->recordMapping(self::LEGACY_TABLE, $legacyUid, self::LOCAL_TABLE, $localUid);
        $this->linkCategory($legacyUid, (int)($legacyRow['category'] ?? 0), $localUid);
    }

    /**
     * @param array<string, mixed> $legacyRow
     * @return array<string, mixed>
     */
    private function productValues(array $legacyRow, int $pid, int $legacyUid): array
    {
        return [
            'pid' => $pid,
            'hidden' => (int)$legacyRow['hidden'],
            'sys_language_uid' => 0,
            'title' => (string)$legacyRow['title'],
            'subtitle' => (string)($legacyRow['subtitle'] ?? ''),
            'slug' => (string)($legacyRow['slug'] ?? ''),
            'description' => (string)($legacyRow['description'] ?? ''),
            'item_number' => (string)$legacyRow['itemnumber'],
            'ean' => (string)$legacyRow['ean'],
            'price' => $this->formatPrice((string)$legacyRow['price']),
            'tax_class' => $this->resolveTaxClass($legacyUid, (int)($legacyRow['taxcat_id'] ?? 0)),
            'in_stock' => (int)$legacyRow['inStock'],
            'basket_min_quantity' => (int)round((float)$legacyRow['basketminquantity']),
            'basket_max_quantity' => (int)round((float)$legacyRow['basketmaxquantity']),
            'weight' => (int)round((float)$legacyRow['weight']),
            'is_offer' => (int)$legacyRow['offer'] > 0 ? 1 : 0,
            'is_highlight' => (int)$legacyRow['highlight'] > 0 ? 1 : 0,
        ];
    }

    private function formatPrice(string $legacyPrice): string
    {
        return number_format((float)$legacyPrice, 2, '.', '');
    }

    /**
     * Legacy taxcat_id code: 0/1 = standard VAT, 2 = reduced VAT, anything else defaults to standard.
     */
    private function resolveTaxClass(int $legacyUid, int $taxcatId): int
    {
        $code = match ($taxcatId) {
            0, 1 => 'standard',
            2 => 'reduced',
            default => 'standard',
        };
        if (!in_array($taxcatId, [0, 1, 2], true)) {
            $this->output->writeln(sprintf(
                '<comment>tt_products uid %d had unknown taxcat_id %d, defaulted to standard tax class.</comment>',
                $legacyUid,
                $taxcatId
            ));
        }
        return $this->fetchTaxClassUidByCode($legacyUid, $code);
    }

    private function fetchTaxClassUidByCode(int $legacyUid, string $code): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_TAXCLASS_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $uid = $queryBuilder->select('uid')->from(self::LOCAL_TAXCLASS_TABLE)
            ->andWhere($queryBuilder->expr()->eq('code', $queryBuilder->createNamedParameter($code)))
            ->andWhere($queryBuilder->expr()->eq('deleted', 0))
            ->executeQuery()->fetchOne();
        if ($uid === false) {
            $this->output->writeln(sprintf(
                '<comment>tt_products uid %d: tax class "%s" not found, run the tax class seeder wizard first.</comment>',
                $legacyUid,
                $code
            ));
            return 0;
        }
        return (int)$uid;
    }

    private function linkCategory(int $legacyUid, int $legacyCategoryUid, int $productLocalUid): void
    {
        if ($legacyCategoryUid === 0) {
            return;
        }
        $categoryLocalUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_CATEGORY_TABLE, $legacyCategoryUid, self::LOCAL_CATEGORY_TABLE);
        if ($categoryLocalUid === null) {
            $this->output->writeln(sprintf(
                '<comment>tt_products uid %d referenced missing category uid %d, left unassigned.</comment>',
                $legacyUid,
                $legacyCategoryUid
            ));
            return;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LOCAL_CATEGORY_MM_TABLE);
        $queryBuilder->insert(self::LOCAL_CATEGORY_MM_TABLE)->values([
            'uid_local' => $productLocalUid,
            'uid_foreign' => $categoryLocalUid,
            'sorting' => 1,
        ])->executeStatement();
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    private function noticeIfImagePresent(array $legacyRow, int $legacyUid): void
    {
        if (trim((string)($legacyRow['image'] ?? '')) === '') {
            return;
        }
        $this->output->writeln(sprintf(
            '<comment>tt_products uid %d had an image; images are out of scope for this migration.</comment>',
            $legacyUid
        ));
    }

    /**
     * @param array<string, mixed> $winner
     * @return array<string, mixed>
     */
    private function overlayValues(array $winner): array
    {
        return [
            'title' => (string)$winner['title'],
            'subtitle' => (string)($winner['subtitle'] ?? ''),
            'slug' => (string)($winner['slug'] ?? ''),
            'item_number' => (string)($winner['itemnumber'] ?? ''),
        ];
    }
}
