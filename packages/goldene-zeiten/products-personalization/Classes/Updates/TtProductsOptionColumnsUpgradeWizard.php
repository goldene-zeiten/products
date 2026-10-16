<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
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
 * Turns the tt_products selection-list columns named in `migration.optionColumns` - ring sizes, engraving
 * fonts - into selection lists of the migrated products, and adds the free text options named in
 * `migration.optionTextColumns` next to them, e.g. for the engraving text tt_products had no column for.
 * Runs after the product migration of products_core.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('productsPersonalization_ttProductsOptionColumns')]
final class TtProductsOptionColumnsUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const OPTION_TABLE = 'tx_productspersonalization_domain_model_option';
    private const ITEM_TABLE = 'tx_productspersonalization_domain_model_optionitem';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyOptionColumns $optionColumns,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Turn tt_products selection lists into personalization options';
    }

    public function getDescription(): string
    {
        return 'Creates an option for every tt_products column named in the extension configuration '
            . 'migration.optionColumns of products_personalization (e.g. ring sizes or an engraving) on the '
            . 'migrated products using it.';
    }

    public function updateNecessary(): bool
    {
        return $this->pendingOptions() !== [];
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
        foreach ($this->pendingOptions() as $option) {
            $this->createOption(...$option);
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
     * The options still to create, in column and product order: the selection list of a column, then the
     * free text next to it.
     *
     * @return array<int, array{productUid: int, column: string, identifier: string, type: string, title: string, sorting: int, values: string[]}>
     */
    private function pendingOptions(): array
    {
        $selectionColumns = $this->optionColumns->getSelectionColumns();
        $textColumns = $this->optionColumns->getTextColumns();
        $pending = [];
        foreach (array_values($this->existingColumns()) as $position => $column) {
            $options = [];
            if (isset($selectionColumns[$column])) {
                $options[] = ['identifier' => $column, 'type' => 'select', 'title' => $selectionColumns[$column], 'sorting' => 2 * $position + 1];
            }
            if (isset($textColumns[$column])) {
                $options[] = ['identifier' => $column . '_text', 'type' => 'text', 'title' => $textColumns[$column], 'sorting' => 2 * $position + 2];
            }
            foreach ($options as $option) {
                $pending = [...$pending, ...$this->pendingForColumn($column, $option)];
            }
        }
        return $pending;
    }

    /**
     * @param array{identifier: string, type: string, title: string, sorting: int} $option
     * @return array<int, array{productUid: int, column: string, identifier: string, type: string, title: string, sorting: int, values: string[]}>
     */
    private function pendingForColumn(string $column, array $option): array
    {
        $pending = [];
        foreach ($this->legacyValues($column) as $legacyUid => $rawValue) {
            $productUid = $this->migrationHelper->resolveLocalUid('tt_products', $legacyUid, 'tx_products_domain_model_product');
            if ($productUid === null || $productUid === 0 || $this->optionExists($productUid, $option['identifier'])) {
                continue;
            }
            $values = array_values(array_unique(array_filter(
                array_map(trim(...), preg_split('/[;\r\n]+/', $rawValue) ?: []),
                static fn(string $value): bool => $value !== ''
            )));
            if ($values !== []) {
                $pending[] = ['productUid' => $productUid, 'column' => $column, ...$option, 'values' => $values];
            }
        }
        return $pending;
    }

    /**
     * @param string[] $values
     */
    private function createOption(int $productUid, string $column, string $identifier, string $type, string $title, int $sorting, array $values): void
    {
        $now = time();
        $pid = $this->productPid($productUid);
        $isText = $type === 'text';
        $connection = $this->connectionPool->getConnectionForTable(self::OPTION_TABLE);
        $connection->insert(self::OPTION_TABLE, [
            'pid' => $pid,
            'tstamp' => $now,
            'crdate' => $now,
            'product' => $productUid,
            'sorting' => $sorting,
            'type' => $type,
            'title' => $title,
            'identifier' => $identifier,
            'required' => $isText ? 0 : 1,
            'personalizing' => $isText ? 1 : 0,
            'surcharge' => '0.00',
            'max_length' => 0,
        ]);
        if ($isText) {
            return;
        }
        $optionUid = (int)$connection->lastInsertId();
        foreach ($values as $index => $value) {
            $this->connectionPool->getConnectionForTable(self::ITEM_TABLE)->insert(self::ITEM_TABLE, [
                'pid' => $pid,
                'tstamp' => $now,
                'crdate' => $now,
                'parent_option' => $optionUid,
                'sorting' => $index + 1,
                'title' => $value,
                'value' => $value,
                'surcharge' => '0.00',
                'personalizing' => $this->optionColumns->isPersonalizingChoice($column, $value) ? 1 : 0,
            ]);
        }
    }

    /**
     * @return string[] the configured columns tt_products has, selection columns first
     */
    private function existingColumns(): array
    {
        $configured = array_unique([...array_keys($this->optionColumns->getSelectionColumns()), ...array_keys($this->optionColumns->getTextColumns())]);
        if ($configured === [] || !$this->migrationHelper->tablesExist('tt_products')) {
            return [];
        }
        $table = $this->connectionPool->getConnectionForTable('tt_products')->createSchemaManager()->introspectTable('tt_products');
        $columns = [];
        foreach ($configured as $column) {
            if ($table->hasColumn($column)) {
                $columns[] = $column;
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

    private function optionExists(int $productUid, string $identifier): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::OPTION_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')
            ->from(self::OPTION_TABLE)
            ->where(
                $queryBuilder->expr()->eq('product', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('identifier', $queryBuilder->createNamedParameter($identifier)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    private function productPid(int $productUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('pid')
            ->from('tx_products_domain_model_product')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
