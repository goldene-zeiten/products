<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Updates;

use GoldeneZeiten\Products\Updates\Prerequisites\CategoryMigrationPrerequisite;
use GoldeneZeiten\Products\Updates\Prerequisites\ProductMigrationPrerequisite;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
// TODO: Migrate to TYPO3\CMS\Core\Attribute\UpgradeWizard once v13 support is dropped.
use TYPO3\CMS\Install\Attribute\UpgradeWizard;
use TYPO3\CMS\Install\Updates\ChattyInterface;
use TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Install\Updates\RepeatableInterface;
use TYPO3\CMS\Install\Updates\UpgradeWizardInterface;

/**
 * Turns legacy flexform-driven tt_products plugin elements into this extension's single-purpose
 * CTypes. A legacy element could select several display modes at once and rendered them back to
 * back, so one such element becomes one new element per selected mode.
 *
 * Elements selecting a mode without an equivalent here are left completely untouched and reported,
 * never migrated half-way - which is also why they keep matching this wizard's query forever, and
 * why iteration is a uid cursor rather than a "fetch pending rows until none are left" loop.
 */
#[UpgradeWizard('products_ttProductsPluginMigration')]
final class TtProductsPluginUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const CONTENT_TABLE = 'tt_content';
    private const LEGACY_LIST_TYPES = ['5', 'tt_products_pi_int', 'tt_products_pi_search'];
    private const SEARCH_LIST_TYPE = 'tt_products_pi_search';
    private const LEGACY_PRODUCT_TABLE = 'tt_products';
    private const LOCAL_PRODUCT_TABLE = 'tx_products_domain_model_product';
    private const LEGACY_CATEGORY_TABLE = 'tt_products_cat';
    private const LOCAL_CATEGORY_TABLE = 'tx_products_domain_model_category';
    private const PRODUCT_SELECTION_CTYPES = ['products_productlist'];
    private const CATEGORY_SELECTION_CTYPES = ['products_productlist', 'products_categorylist'];

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyPluginModeMap $modeMap,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
        private readonly CategoryMigrationPrerequisite $categoryMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Migrate tt_products plugin content elements';
    }

    public function getDescription(): string
    {
        return 'Converts legacy flexform-driven tt_products plugin elements (list_type 5, pi_int and '
            . 'pi_search) into this extension\'s single-purpose CTypes, splitting a multi-mode element '
            . 'into one element per mode and remapping its product/category selections. Elements using '
            . 'legacy features without an equivalent here are left untouched and reported for manual review.';
    }

    public function updateNecessary(): bool
    {
        if (!$this->listTypeColumnExists()) {
            return false;
        }
        foreach ($this->legacyRows() as $row) {
            if ($this->resolveTargets($row) !== []) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        if (!$this->listTypeColumnExists()) {
            $this->output->writeln(
                '<error>tt_content has no "list_type" column (removed in TYPO3 v14), so legacy '
                    . 'tt_products plugin elements can no longer be identified. This wizard had to run '
                    . 'before that column was dropped; any remaining elements must be rebuilt by hand.</error>'
            );
            return true;
        }
        if (!$this->prerequisitesFulfilled()) {
            return false;
        }
        foreach ($this->legacyRows() as $row) {
            $this->migrateRow($row);
        }
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
        ];
    }

    private function prerequisitesFulfilled(): bool
    {
        if (!$this->productMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products still has unmigrated rows; run the product migration '
                    . 'wizard (products_ttProductsProductMigration) first.</error>'
            );
            return false;
        }
        if (!$this->categoryMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products_cat still has unmigrated rows; run the category migration '
                    . 'wizard (products_ttProductsCategoryMigration) first.</error>'
            );
            return false;
        }
        return true;
    }

    /**
     * Walks the legacy elements batch-wise via a uid cursor. Untouchable elements stay in the
     * result set of the underlying query, so advancing past the last seen uid is what terminates
     * this - never the absence of matching rows.
     *
     * @return \Generator<array<string, mixed>>
     */
    private function legacyRows(): \Generator
    {
        $lastUid = 0;
        while (true) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CONTENT_TABLE);
            $queryBuilder->getRestrictions()->removeAll();
            $rows = $queryBuilder->select('*')
                ->from(self::CONTENT_TABLE)
                ->where(
                    $queryBuilder->expr()->eq('CType', $queryBuilder->createNamedParameter('list')),
                    $queryBuilder->expr()->in(
                        'list_type',
                        $queryBuilder->createNamedParameter(self::LEGACY_LIST_TYPES, Connection::PARAM_STR_ARRAY)
                    ),
                    $queryBuilder->expr()->gt('uid', $queryBuilder->createNamedParameter($lastUid, Connection::PARAM_INT))
                )
                ->orderBy('uid')
                ->setMaxResults(LegacyMigrationHelper::BATCH_SIZE)
                ->executeQuery()
                ->fetchAllAssociative();
            if ($rows === []) {
                return;
            }
            foreach ($rows as $row) {
                $lastUid = (int)$row['uid'];
                yield $row;
            }
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function migrateRow(array $row): void
    {
        $targets = $this->resolveTargets($row);
        if ($targets === []) {
            $this->reportUntouched($row);
            return;
        }
        $selections = $this->resolveSelections($row);

        $firstTarget = array_shift($targets);
        $this->rewriteRow((int)$row['uid'], $firstTarget, $selections);
        $sortingOffset = 1;
        foreach ($targets as $target) {
            $this->insertSibling($row, $target, $selections, $sortingOffset);
            $sortingOffset++;
        }
    }

    /**
     * Every selected mode must have an equivalent, otherwise the element is left alone entirely -
     * migrating only the mappable half would silently drop content the editor put on the page.
     * Several modes can share a target (the checkout steps all do), which collapses into one element.
     *
     * @param array<string, mixed> $row
     * @return array<int, array{ctype: string, fields: array<string, string>}>
     */
    private function resolveTargets(array $row): array
    {
        $modes = $this->selectedModes($row);
        if ($modes === []) {
            return [];
        }
        if ((string)$row['list_type'] === self::SEARCH_LIST_TYPE) {
            return [$this->searchTarget($row, $modes[0])];
        }
        $targets = [];
        foreach ($modes as $mode) {
            $target = $this->modeMap->resolveMode($mode);
            if ($target === null) {
                return [];
            }
            $targets[$this->targetSignature($target)] = $target;
        }
        return array_values($targets);
    }

    /**
     * @param array<string, mixed> $row
     * @return string[]
     */
    private function selectedModes(array $row): array
    {
        $flexForm = $this->parseFlexForm($row);
        $displayMode = (string)($flexForm['display_mode'] ?? '');
        return array_values(array_filter(array_map('trim', explode(',', $displayMode))));
    }

    /**
     * @param array<string, mixed> $row
     * @return array{ctype: string, fields: array<string, string>}
     */
    private function searchTarget(array $row, string $displayMode): array
    {
        $fields = ['tx_products_search_browse_mode' => $this->modeMap->resolveSearchMode($displayMode)];
        $searchField = (string)($this->parseFlexForm($row)['fields'] ?? '');
        if ($searchField !== '') {
            $fields['tx_products_search_field'] = $searchField;
        }
        return ['ctype' => 'products_search', 'fields' => $fields];
    }

    /**
     * @param array{ctype: string, fields: array<string, string>} $target
     */
    private function targetSignature(array $target): string
    {
        return $target['ctype'] . '|' . http_build_query($target['fields']);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string> tt_content column => value
     */
    private function resolveSelections(array $row): array
    {
        $flexForm = $this->parseFlexForm($row);
        $selections = [];
        $products = $this->remapUids(
            (string)($flexForm['productSelection'] ?? ''),
            self::LEGACY_PRODUCT_TABLE,
            self::LOCAL_PRODUCT_TABLE
        );
        if ($products !== '') {
            $selections['records'] = $products;
        }
        $categories = $this->remapUids(
            (string)($flexForm['categorySelection'] ?? ''),
            self::LEGACY_CATEGORY_TABLE,
            self::LOCAL_CATEGORY_TABLE
        );
        if ($categories !== '') {
            $selections['tx_products_category'] = $categories;
        }
        return $selections;
    }

    /**
     * Legacy group fields may store uids prefixed with their table name ("tt_products_12").
     */
    private function remapUids(string $legacyValue, string $legacyTable, string $localTable): string
    {
        $localUids = [];
        foreach (array_filter(array_map('trim', explode(',', $legacyValue))) as $legacyUid) {
            $uid = (int)str_replace($legacyTable . '_', '', $legacyUid);
            $localUid = $this->migrationHelper->resolveLocalUid($legacyTable, $uid, $localTable);
            if ($localUid !== null) {
                $localUids[] = (string)$localUid;
            }
        }
        return implode(',', $localUids);
    }

    /**
     * @param array{ctype: string, fields: array<string, string>} $target
     * @param array<string, string> $selections
     */
    private function rewriteRow(int $uid, array $target, array $selections): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CONTENT_TABLE);
        $queryBuilder->update(self::CONTENT_TABLE)
            ->set('CType', $target['ctype'])
            ->set('list_type', '')
            ->set('pi_flexform', '');
        foreach ($this->targetValues($target, $selections) as $column => $value) {
            $queryBuilder->set($column, $value);
        }
        $queryBuilder
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeStatement();
    }

    /**
     * @param array<string, mixed> $row
     * @param array{ctype: string, fields: array<string, string>} $target
     * @param array<string, string> $selections
     */
    private function insertSibling(array $row, array $target, array $selections, int $sortingOffset): void
    {
        $values = $row;
        unset($values['uid']);
        $values['CType'] = $target['ctype'];
        $values['list_type'] = '';
        $values['pi_flexform'] = '';
        $values['sorting'] = (int)$row['sorting'] + $sortingOffset;
        foreach ($this->targetValues($target, $selections) as $column => $value) {
            $values[$column] = $value;
        }
        $this->connectionPool->getConnectionForTable(self::CONTENT_TABLE)->insert(self::CONTENT_TABLE, $values);
    }

    /**
     * A selection only travels to a CType that actually renders it - `records` exists on the product
     * list alone, the category tree on both list elements.
     *
     * @param array{ctype: string, fields: array<string, string>} $target
     * @param array<string, string> $selections
     * @return array<string, string>
     */
    private function targetValues(array $target, array $selections): array
    {
        $values = $target['fields'];
        if (isset($selections['records']) && in_array($target['ctype'], self::PRODUCT_SELECTION_CTYPES, true)) {
            $values['records'] = $selections['records'];
        }
        if (isset($selections['tx_products_category']) && in_array($target['ctype'], self::CATEGORY_SELECTION_CTYPES, true)) {
            $values['tx_products_category'] = $selections['tx_products_category'];
        }
        return $values;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, string>
     */
    private function parseFlexForm(array $row): array
    {
        $flexFormXml = (string)($row['pi_flexform'] ?? '');
        if (trim($flexFormXml) === '') {
            return [];
        }
        $parsed = GeneralUtility::xml2array($flexFormXml);
        if (!is_array($parsed)) {
            return [];
        }
        $values = [];
        foreach (($parsed['data']['sDEF']['lDEF'] ?? []) as $field => $definition) {
            $values[(string)$field] = (string)($definition['vDEF'] ?? '');
        }
        return $values;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function reportUntouched(array $row): void
    {
        $modes = $this->selectedModes($row);
        $this->output->writeln(sprintf(
            '<comment>tt_content uid %d on page %d ("%s") uses legacy plugin "%s" with unsupported '
                . 'mode(s) %s and was left untouched - migrate it by hand or delete it.</comment>',
            (int)$row['uid'],
            (int)$row['pid'],
            $this->pageTitle((int)$row['pid']),
            (string)$row['list_type'],
            $modes === [] ? '(none selected)' : implode(', ', $modes)
        ));
    }

    /**
     * The legacy plugins were never ported to CType registration (not even in tt_products 3.5.2, its
     * last v13 release), so the elements are only findable via `list_type` - a column TYPO3 v14 drops.
     */
    private function listTypeColumnExists(): bool
    {
        $schemaManager = $this->connectionPool->getConnectionForTable(self::CONTENT_TABLE)->createSchemaManager();
        foreach ($schemaManager->listTableColumns(self::CONTENT_TABLE) as $column) {
            if ($column->getName() === 'list_type') {
                return true;
            }
        }
        return false;
    }

    private function pageTitle(int $pageUid): string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('pages');
        $queryBuilder->getRestrictions()->removeAll();
        $title = $queryBuilder->select('title')
            ->from('pages')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($pageUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
        return $title === false ? '' : (string)$title;
    }
}
