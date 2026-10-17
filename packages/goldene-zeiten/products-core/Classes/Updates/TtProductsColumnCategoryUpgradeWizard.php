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
 * Turns legacy grouping columns of tt_products - a manufacturer, tags - into category trees: a root
 * category per column configured in `migration.columnCategories`, a child per distinct value, and every
 * migrated product assigned to the categories of its values. Runs after the product migration.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsColumnCategories')]
final class TtProductsColumnCategoryUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyMigrationConfiguration $migrationConfiguration,
        private readonly LegacyColumnCategories $columnCategories,
        private readonly LegacyCategoryAssigner $categoryAssigner,
        private readonly StorageFolderResolver $storageFolderResolver,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Turn tt_products grouping columns into categories';
    }

    public function getDescription(): string
    {
        return 'Creates a category tree for every tt_products column named in the extension configuration '
            . 'migration.columnCategories (e.g. a manufacturer or tag column) and assigns the migrated products.';
    }

    public function updateNecessary(): bool
    {
        foreach ($this->assignments() as [$productUid, $column, , $value]) {
            $categoryUid = $this->columnCategories->findValueCategory($column, $value);
            if ($categoryUid === null || !$this->categoryAssigner->isAssigned($productUid, $categoryUid)) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        if (!$this->productMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products still has unmigrated rows; run the product migration '
                    . 'wizard (products_ttProductsProductMigration) first.</error>'
            );
            return false;
        }
        $pid = $this->storageFolderResolver->resolve();
        $merged = [];
        foreach ($this->assignments() as [$productUid, $column, $rootTitle, $value]) {
            $spelling = $this->columnCategories->mergedSpelling($column, $value);
            if ($spelling !== null) {
                $merged[sprintf('tt_products.%s "%s" was merged into the category "%s".', $column, $value, $spelling)] = true;
            }
            $this->categoryAssigner->assign($productUid, $this->columnCategories->valueCategory($column, $rootTitle, $value, $pid));
        }
        foreach (array_keys($merged) as $message) {
            $this->output->writeln('<comment>' . $message . '</comment>');
        }
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
     * Every product - category value pair the configured columns call for, in column and product order.
     *
     * @return array<int, array{0: int, 1: string, 2: string, 3: string}> [product uid, column, root title, value]
     */
    private function assignments(): array
    {
        $assignments = [];
        foreach ($this->existingColumns() as $column => $rootTitle) {
            foreach ($this->legacyValues($column) as $legacyUid => $rawValue) {
                $productUid = $this->migrationHelper->resolveLocalUid('tt_products', $legacyUid, 'tx_products_domain_model_product');
                if ($productUid === null || $productUid === 0) {
                    continue;
                }
                foreach ($this->columnCategories->values($column, $rawValue) as $value) {
                    $assignments[] = [$productUid, $column, $rootTitle, $value];
                }
            }
        }
        return $assignments;
    }

    /**
     * @return array<string, string> column => root title, for the configured columns tt_products has
     */
    private function existingColumns(): array
    {
        $configured = $this->migrationConfiguration->getColumnCategories();
        if ($configured === [] || !$this->migrationHelper->tablesExist('tt_products')) {
            return [];
        }
        $table = $this->connectionPool->getConnectionForTable('tt_products')->createSchemaManager()->introspectTable('tt_products');
        $columns = [];
        foreach ($configured as $column => $rootTitle) {
            if ($table->hasColumn($column)) {
                $columns[$column] = $rootTitle;
            } elseif (isset($this->output)) {
                $this->output->writeln(sprintf('<comment>tt_products has no column "%s"; it is left out.</comment>', $column));
            }
        }
        return $columns;
    }

    /**
     * @return array<int, string> legacy uid => raw column value
     */
    private function legacyValues(string $column): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tt_products');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid', $column)
            ->from('tt_products')
            ->where($queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('uid')
            ->executeQuery();
        $values = [];
        while ($row = $result->fetchAssociative()) {
            $values[(int)$row['uid']] = (string)($row[$column] ?? '');
        }
        return $values;
    }
}
