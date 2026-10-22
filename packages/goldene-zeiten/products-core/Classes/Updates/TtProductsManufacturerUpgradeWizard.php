<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use GoldeneZeiten\Products\Core\Backend\StorageFolderResolver;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\ProductMigrationPrerequisite;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Turns the tt_products column named in `migration.manufacturerColumn` into manufacturer records: one per
 * distinct value, linked to every migrated product that has it, completed from `tt_products.address` (a
 * tt_address record) and `tt_products.www`. Runs after the product migration. Values that look alike
 * ("Stelmach" / "Stelmach Trauringe") are reported, not merged.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsManufacturers')]
final class TtProductsManufacturerUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyMigrationConfiguration $migrationConfiguration,
        private readonly LegacyManufacturers $manufacturers,
        private readonly StorageFolderResolver $storageFolderResolver,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Turn the tt_products manufacturer column into manufacturer records';
    }

    public function getDescription(): string
    {
        return 'Creates a manufacturer record per distinct value of the tt_products column named in the '
            . 'extension configuration migration.manufacturerColumn and links the migrated products.';
    }

    public function updateNecessary(): bool
    {
        $column = $this->existingColumn();
        if ($column === null) {
            return false;
        }
        foreach ($this->legacyRows($column) as $row) {
            $productUid = $this->productUid($row['uid']);
            if ($productUid !== null && ($this->manufacturers->find($column, $row['name']) === null || $this->linkedManufacturer($productUid) === 0)) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        $column = $this->existingColumn();
        if ($column === null) {
            return true;
        }
        if (!$this->productMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products still has unmigrated rows; run the product migration '
                    . 'wizard (products_ttProductsProductMigration) first.</error>'
            );
            return false;
        }
        $pid = $this->storageFolderResolver->resolve();
        foreach ($this->legacyRows($column) as $row) {
            $productUid = $this->productUid($row['uid']);
            if ($productUid !== null) {
                $this->migrateProduct($productUid, $this->manufacturers->findOrCreate($column, $row['name'], $pid), $row);
            }
        }
        $this->reportLookAlikes($column);
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class, ProductMigrationPrerequisite::class];
    }

    /**
     * @param array{uid: int, name: string, address: int, www: string} $row
     */
    private function migrateProduct(int $productUid, int $manufacturerUid, array $row): void
    {
        if ($this->linkedManufacturer($productUid) === 0) {
            $this->connectionPool->getConnectionForTable('tx_products_domain_model_product')
                ->update('tx_products_domain_model_product', ['manufacturer' => $manufacturerUid], ['uid' => $productUid]);
        }
        if ($row['address'] > 0 && $this->migrationHelper->tablesExist('tt_address')) {
            $this->manufacturers->complete($manufacturerUid, $this->manufacturers->legacyAddress($row['address']));
        }
        if ($row['www'] !== '') {
            $this->manufacturers->complete($manufacturerUid, ['website' => $row['www']]);
        }
    }

    private function reportLookAlikes(string $column): void
    {
        foreach ($this->manufacturers->lookAlikes($column) as [$short, $long]) {
            $this->output->writeln(sprintf(
                '<comment>The manufacturers "%s" / "%s" look like duplicates; merge them by hand if they are one.</comment>',
                $short,
                $long
            ));
        }
    }

    private function productUid(int $legacyUid): ?int
    {
        $productUid = $this->migrationHelper->resolveLocalUid('tt_products', $legacyUid, 'tx_products_domain_model_product');
        return $productUid === null || $productUid === 0 ? null : $productUid;
    }

    private function linkedManufacturer(int $productUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('manufacturer')
            ->from('tx_products_domain_model_product')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * The configured column when tt_products has it.
     */
    private function existingColumn(): ?string
    {
        $column = $this->migrationConfiguration->getManufacturerColumn();
        if ($column === '' || !$this->migrationHelper->tablesExist('tt_products')) {
            return null;
        }
        $table = $this->connectionPool->getConnectionForTable('tt_products')->createSchemaManager()->introspectTable('tt_products');
        if ($table->hasColumn($column)) {
            return $column;
        }
        if (isset($this->output)) {
            $this->output->writeln(sprintf('<comment>tt_products has no column "%s"; no manufacturers are migrated.</comment>', $column));
        }
        return null;
    }

    /**
     * The legacy products with a manufacturer name, in uid order; `address` and `www` are 0 and empty where
     * tt_products lacks the column.
     *
     * @return array<int, array{uid: int, name: string, address: int, www: string}>
     */
    private function legacyRows(string $column): array
    {
        $table = $this->connectionPool->getConnectionForTable('tt_products')->createSchemaManager()->introspectTable('tt_products');
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_products');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('*')
            ->from('tt_products')
            ->where($queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('uid')
            ->executeQuery();
        $rows = [];
        while ($row = $result->fetchAssociative()) {
            $name = trim((string)($row[$column] ?? ''));
            if ($name !== '') {
                $rows[] = [
                    'uid' => (int)$row['uid'],
                    'name' => $name,
                    'address' => $table->hasColumn('address') ? (int)$row['address'] : 0,
                    'www' => $table->hasColumn('www') ? trim((string)$row['www']) : '',
                ];
            }
        }
        return $rows;
    }
}
