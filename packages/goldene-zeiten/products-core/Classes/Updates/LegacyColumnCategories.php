<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\DataHandling\SlugHelper;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The category trees built from legacy grouping columns ({@see LegacyMigrationConfiguration::getColumnCategories()}):
 * a root category per column, recorded in the migration map, and one child per distinct value, found by its
 * title regardless of case and accents - as the legacy shop's collation compared them.
 */
final class LegacyColumnCategories
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyMigrationConfiguration $migrationConfiguration,
    ) {}

    /**
     * The values a legacy row holds in the column; a multi-value column is split at commas, semicolons and
     * line breaks.
     *
     * @return string[]
     */
    public function values(string $column, string $rawValue): array
    {
        $values = in_array($column, $this->migrationConfiguration->getList('columnCategoriesMultiValue'), true)
            ? preg_split('/[,;\r\n]+/', $rawValue) ?: []
            : [$rawValue];
        return array_values(array_filter(array_map(trim(...), $values), static fn(string $value): bool => $value !== ''));
    }

    public function findValueCategory(string $column, string $value): ?int
    {
        return $this->findValueCategoryRow($column, $value)['uid'] ?? null;
    }

    /**
     * The title of the category the value belongs to, when it is spelled differently; null for a new value or the
     * same spelling.
     */
    public function mergedSpelling(string $column, string $value): ?string
    {
        $title = $this->findValueCategoryRow($column, $value)['title'] ?? null;
        return $title !== null && $title !== $value ? $title : null;
    }

    /**
     * @return array{uid: int, title: string}|null
     */
    private function findValueCategoryRow(string $column, string $value): ?array
    {
        $rootUid = $this->migrationHelper->resolveLocalUid($this->mapKey($column), 0, 'tx_products_domain_model_category');
        if ($rootUid === null) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_category');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid', 'title')
            ->from('tx_products_domain_model_category')
            ->where(
                $queryBuilder->expr()->eq('parent_category', $queryBuilder->createNamedParameter($rootUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery();
        $key = $this->comparisonKey($value);
        while ($row = $result->fetchAssociative()) {
            if ($this->comparisonKey((string)$row['title']) === $key) {
                return ['uid' => (int)$row['uid'], 'title' => (string)$row['title']];
            }
        }
        return null;
    }

    /**
     * "Rosègold" and "roségold" are the same value: lower case, accents dropped.
     */
    private function comparisonKey(string $title): string
    {
        $decomposed = \Normalizer::normalize(mb_strtolower($title), \Normalizer::FORM_D);
        return preg_replace('/\p{Mn}+/u', '', $decomposed === false ? mb_strtolower($title) : $decomposed) ?? $title;
    }

    public function valueCategory(string $column, string $rootTitle, string $value, int $pid): int
    {
        return $this->findValueCategory($column, $value)
            ?? $this->insertCategory($value, $this->rootCategory($column, $rootTitle, $pid), $pid);
    }

    private function rootCategory(string $column, string $title, int $pid): int
    {
        $rootUid = $this->migrationHelper->resolveLocalUid($this->mapKey($column), 0, 'tx_products_domain_model_category');
        if ($rootUid !== null) {
            return $rootUid;
        }
        $rootUid = $this->insertCategory($title, 0, $pid);
        $this->migrationHelper->recordMapping($this->mapKey($column), 0, 'tx_products_domain_model_category', $rootUid);
        return $rootUid;
    }

    private function insertCategory(string $title, int $parentUid, int $pid): int
    {
        $slugHelper = GeneralUtility::makeInstance(SlugHelper::class, 'tx_products_domain_model_category', 'slug', []);
        $connection = $this->connectionPool->getConnectionForTable('tx_products_domain_model_category');
        $now = (int)($GLOBALS['EXEC_TIME'] ?? time());
        $connection->insert('tx_products_domain_model_category', [
            'tstamp' => $now,
            'crdate' => $now,
            'pid' => $pid,
            'title' => $title,
            'slug' => $slugHelper->sanitize($title),
            'parent_category' => $parentUid,
        ]);
        return (int)$connection->lastInsertId();
    }

    private function mapKey(string $column): string
    {
        return 'tt_products.' . $column;
    }
}
