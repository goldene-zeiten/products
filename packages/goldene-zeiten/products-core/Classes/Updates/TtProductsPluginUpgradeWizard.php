<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use GoldeneZeiten\Products\Core\Updates\Prerequisites\CategoryMigrationPrerequisite;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\ColumnCategoryPrerequisite;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\ProductMigrationPrerequisite;
use GoldeneZeiten\Products\Core\Updates\Prerequisites\StorageFolderCategoryPrerequisite;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
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
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
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
    private const PRODUCT_SELECTION_CTYPES = ['productscore_productlist'];
    private const CATEGORY_SELECTION_CTYPES = ['productscore_productlist', 'productscore_categorylist'];
    private const MIGRATED_FLEXFORM_FIELDS = ['display_mode', 'productSelection', 'categorySelection', 'fields'];
    /**
     * `conf.tt_products.ALL.orderBy` => tx_products_sorting; uid order stays the list's default order.
     */
    private const SORTINGS = [
        'uid' => '',
        'uid asc' => '',
        'title' => 'title',
        'title asc' => 'title',
        'price' => 'price_asc',
        'price asc' => 'price_asc',
        'price desc' => 'price_desc',
        'crdate desc' => 'newest',
        'tstamp desc' => 'newest',
    ];

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyPluginModeMap $modeMap,
        private readonly ProductMigrationPrerequisite $productMigrationPrerequisite,
        private readonly CategoryMigrationPrerequisite $categoryMigrationPrerequisite,
        private readonly StorageFolderCategoryPrerequisite $storageFolderCategoryPrerequisite,
        private readonly ColumnCategoryPrerequisite $columnCategoryPrerequisite,
        private readonly LegacyMigrationConfiguration $migrationConfiguration,
        private readonly LegacyColumnCategories $columnCategories,
        private readonly LegacyPageTemplateSettings $pageTemplateSettings,
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
        $splitDefaults = [];
        foreach ($this->legacyRows(false) as $row) {
            $splitDefaults[(int)$row['uid']] = $this->migrateRow($row, null);
        }
        foreach ($this->legacyRows(true) as $row) {
            $this->migrateRow($row, $splitDefaults[(int)$row['l18n_parent']] ?? []);
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
            StorageFolderCategoryPrerequisite::class,
            ColumnCategoryPrerequisite::class,
        ];
    }

    private function prerequisitesFulfilled(): bool
    {
        $checks = [
            'tt_products still has unmigrated rows; run the product migration wizard (products_ttProductsProductMigration) first.'
                => $this->productMigrationPrerequisite,
            'tt_products_cat still has unmigrated rows; run the category migration wizard (products_ttProductsCategoryMigration) first.'
                => $this->categoryMigrationPrerequisite,
            'The storage folders are not turned into categories yet; run the storage folder category wizard (products_ttProductsStorageFolderCategories) first.'
                => $this->storageFolderCategoryPrerequisite,
            'The grouping columns are not turned into categories yet; run the column category wizard (products_ttProductsColumnCategories) first.'
                => $this->columnCategoryPrerequisite,
        ];
        foreach ($checks as $message => $prerequisite) {
            if (!$prerequisite->isFulfilled()) {
                $this->output->writeln('<error>' . $message . '</error>');
                return false;
            }
        }
        return true;
    }

    /**
     * Walks the legacy elements batch-wise via a uid cursor. Untouchable elements stay in the
     * result set of the underlying query, so advancing past the last seen uid is what terminates
     * this - never the absence of matching rows.
     *
     * @param bool|null $translations true for translated elements only, false for default-language
     *                                ones only, null for both
     * @return \Generator<array<string, mixed>>
     */
    private function legacyRows(?bool $translations = null): \Generator
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
                    $queryBuilder->expr()->gt('uid', $queryBuilder->createNamedParameter($lastUid, Connection::PARAM_INT)),
                    ...$this->translationConditions($queryBuilder, $translations)
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
     * @return string[]
     */
    private function translationConditions(QueryBuilder $queryBuilder, ?bool $translations): array
    {
        if ($translations === null) {
            return [];
        }
        $zero = $queryBuilder->createNamedParameter(0, Connection::PARAM_INT);
        return [
            $translations ? $queryBuilder->expr()->gt('l18n_parent', $zero) : $queryBuilder->expr()->eq('l18n_parent', $zero),
        ];
    }

    /**
     * A translated element is split like its default-language element, and each translated part
     * points at the default-language part carrying the same target.
     *
     * @param array<string, mixed> $row
     * @param array<string, int>|null $defaultParts target signature => uid of the default-language part,
     *                                              null for a default-language element
     * @return array<string, int> target signature => uid of the element now carrying that target
     */
    private function migrateRow(array $row, ?array $defaultParts): array
    {
        $targets = $this->resolveTargets($row);
        if ($targets === []) {
            $this->reportUntouched($row);
            return [];
        }
        $this->reportDroppedSettings($row);
        $selections = $this->resolveSelections($row);
        $parts = [];
        foreach ($targets as $sortingOffset => $target) {
            $signature = $this->targetSignature($target);
            if ($sortingOffset === 0) {
                $this->rewriteRow((int)$row['uid'], $target, $selections, $defaultParts[$signature] ?? null);
                $parts[$signature] = (int)$row['uid'];
                continue;
            }
            $translationParent = $defaultParts === null ? null : ($defaultParts[$signature] ?? 0);
            $parts[$signature] = $this->insertSibling($row, $target, $selections, $sortingOffset, $translationParent);
        }
        return $parts;
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
        return ['ctype' => 'productssearch_search', 'fields' => $fields];
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
        $categories = array_filter(array_unique(array_merge(
            explode(',', $this->remapUids((string)($flexForm['categorySelection'] ?? ''), self::LEGACY_CATEGORY_TABLE, self::LOCAL_CATEGORY_TABLE)),
            array_values($this->startingPointCategories($row)),
            array_values($this->pidListCategories($row)),
            $this->productWhereCategories((string)($flexForm['product_where'] ?? '')) ?? []
        )));
        if ($categories !== []) {
            $selections['tx_products_category'] = implode(',', $categories);
        }
        $limit = $this->pageTemplateSettings->getLimit((int)$row['pid']);
        if ($limit > 0) {
            $selections['tx_products_items_per_page'] = (string)$limit;
        }
        $sorting = self::SORTINGS[strtolower(preg_replace('/\s+/', ' ', $this->pageTemplateSettings->getOrderBy((int)$row['pid'])) ?? '')] ?? '';
        if ($sorting !== '') {
            $selections['tx_products_sorting'] = $sorting;
        }
        return $selections;
    }

    /**
     * The categories the folders of `plugin.tt_products.pid_list` became - only for an element without a
     * starting point of its own, which tt_products preferred over pid_list.
     *
     * @param array<string, mixed> $row
     * @return array<int, string> folder uid => category uid
     */
    private function pidListCategories(array $row): array
    {
        if ($this->startingPoints($row) !== []) {
            return [];
        }
        $categories = [];
        foreach ($this->pageTemplateSettings->getPidList((int)$row['pid']) as $folderUid) {
            $categoryUid = $this->migrationHelper->resolveLocalUid('pages', $folderUid, self::LOCAL_CATEGORY_TABLE);
            if ($categoryUid !== null && $categoryUid > 0) {
                $categories[$folderUid] = (string)$categoryUid;
            }
        }
        return $categories;
    }

    /**
     * The categories a product_where filter selects when it only matches values of columns that became
     * categories - `quality LIKE '%White gold%'`, alternatives joined by OR. Null for anything else.
     *
     * @return string[]|null
     */
    private function productWhereCategories(string $productWhere): ?array
    {
        $columns = $this->migrationConfiguration->getColumnCategories();
        if (trim($productWhere) === '' || $columns === []) {
            return null;
        }
        $categories = [];
        foreach (preg_split('/\s+OR\s+/i', trim($productWhere)) ?: [] as $condition) {
            $matched = preg_match('/^\(?\s*`?([a-z0-9_]+)`?\s+(?:LIKE|=)\s+([\'"])%?([^%\'"]+)%?\2\s*\)?$/i', trim($condition), $parts);
            $categoryUid = $matched === 1 && isset($columns[$parts[1]]) ? $this->columnCategories->findValueCategory($parts[1], trim($parts[3])) : null;
            if ($categoryUid === null) {
                return null;
            }
            $categories[] = (string)$categoryUid;
        }
        return $categories;
    }

    /**
     * @param array<string, mixed> $row
     * @return int[] the page uids of the element's starting point
     */
    private function startingPoints(array $row): array
    {
        return array_values(array_filter(array_map('intval', explode(',', (string)($row['pages'] ?? '')))));
    }

    /**
     * The categories the storage folder wizard created for the element's starting point.
     *
     * @param array<string, mixed> $row
     * @return array<int, string> page uid => category uid
     */
    private function startingPointCategories(array $row): array
    {
        $categories = [];
        foreach ($this->startingPoints($row) as $pageUid) {
            $categoryUid = $this->migrationHelper->resolveLocalUid('pages', $pageUid, self::LOCAL_CATEGORY_TABLE);
            if ($categoryUid !== null && $categoryUid > 0) {
                $categories[$pageUid] = (string)$categoryUid;
            }
        }
        return $categories;
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
            if ($localUid !== null && $localUid !== 0) {
                $localUids[] = (string)$localUid;
            }
        }
        return implode(',', $localUids);
    }

    /**
     * @param array{ctype: string, fields: array<string, string>} $target
     * @param array<string, string> $selections
     */
    private function rewriteRow(int $uid, array $target, array $selections, ?int $translationParent): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::CONTENT_TABLE);
        $queryBuilder->update(self::CONTENT_TABLE)
            ->set('CType', $target['ctype'])
            ->set('list_type', '')
            ->set('pi_flexform', '');
        foreach ($this->translationParentValues($translationParent) as $column => $value) {
            $queryBuilder->set($column, $value);
        }
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
    private function insertSibling(array $row, array $target, array $selections, int $sortingOffset, ?int $translationParent): int
    {
        $values = $row;
        unset($values['uid']);
        $values['CType'] = $target['ctype'];
        $values['list_type'] = '';
        $values['pi_flexform'] = '';
        $values['sorting'] = (int)$row['sorting'] + $sortingOffset;
        $values = array_merge($values, $this->translationParentValues($translationParent), $this->targetValues($target, $selections));
        $connection = $this->connectionPool->getConnectionForTable(self::CONTENT_TABLE);
        $connection->insert(self::CONTENT_TABLE, $values);
        return (int)$connection->lastInsertId();
    }

    /**
     * @return array<string, int>
     */
    private function translationParentValues(?int $translationParent): array
    {
        if ($translationParent === null) {
            return [];
        }
        return [
            'l18n_parent' => $translationParent,
            'l10n_source' => $translationParent,
        ];
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
        foreach (['tx_products_category', 'tx_products_items_per_page', 'tx_products_sorting'] as $field) {
            if (isset($selections[$field]) && in_array($target['ctype'], self::CATEGORY_SELECTION_CTYPES, true)) {
                $values[$field] = $selections[$field];
            }
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
     * Lists FlexForm fields and the starting point (`pages`, the legacy `pid_list`) the new element
     * does not take over, so the integrator can rebuild a filtered list, e.g. as a category selection.
     *
     * @param array<string, mixed> $row
     */
    private function reportDroppedSettings(array $row): void
    {
        $flexForm = $this->parseFlexForm($row);
        $dropped = array_diff_key(
            array_filter($flexForm, static fn(string $value): bool => trim($value) !== '' && trim($value) !== '0'),
            array_flip(self::MIGRATED_FLEXFORM_FIELDS)
        );
        if ($this->productWhereCategories((string)($flexForm['product_where'] ?? '')) !== null) {
            unset($dropped['product_where']);
        }
        $unmappedStartingPoints = array_diff($this->startingPoints($row), array_keys($this->startingPointCategories($row)));
        if ($unmappedStartingPoints !== []) {
            $dropped['pages'] = implode(',', $unmappedStartingPoints);
        }
        $unmappedPidList = $this->startingPoints($row) === []
            ? array_diff($this->pageTemplateSettings->getPidList((int)$row['pid']), array_keys($this->pidListCategories($row)))
            : [];
        if ($unmappedPidList !== []) {
            $dropped['pid_list'] = implode(',', $unmappedPidList);
        }
        $orderBy = $this->pageTemplateSettings->getOrderBy((int)$row['pid']);
        if ($orderBy !== '' && !isset(self::SORTINGS[strtolower(preg_replace('/\s+/', ' ', $orderBy) ?? '')])) {
            $dropped['orderBy'] = $orderBy;
        }
        if ($dropped === []) {
            return;
        }
        $settings = [];
        foreach ($dropped as $field => $value) {
            $settings[] = sprintf('%s = "%s"', $field, OutputFormatter::escape($value));
        }
        $this->output->writeln(sprintf(
            '<comment>tt_content uid %d on page %d ("%s") was migrated without these legacy settings: %s</comment>',
            (int)$row['uid'],
            (int)$row['pid'],
            $this->pageTitle((int)$row['pid']),
            implode(', ', $settings)
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
