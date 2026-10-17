<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use GoldeneZeiten\Products\Core\Updates\Prerequisites\ArticleMigrationPrerequisite;
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
 * Turns article columns of tt_products that told the articles of a product apart - a material, an alloy -
 * into attributes: one attribute per column named in `migration.articleAttributeColumns`, one value per
 * distinct entry, and every migrated article linked to its value. Runs after the article migration.
 *
 * Public, because the Install Tool instantiates upgrade wizards through makeInstance, which only
 * injects dependencies into a public service.
 */
#[Autoconfigure(public: true)]
#[UpgradeWizard('products_ttProductsArticleAttributes')]
final class TtProductsArticleAttributeUpgradeWizard implements UpgradeWizardInterface, ChattyInterface, RepeatableInterface
{
    private const LEGACY_TABLE = 'tt_products_articles';
    private const ARTICLE_TABLE = 'tx_products_domain_model_article';
    private const ATTRIBUTE_TABLE = 'tx_products_domain_model_attribute';
    private const VALUE_TABLE = 'tx_products_domain_model_attributevalue';
    private const MM_TABLE = 'tx_products_article_attributevalue_mm';

    private OutputInterface $output;

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationHelper $migrationHelper,
        private readonly LegacyMigrationConfiguration $migrationConfiguration,
        private readonly ArticleMigrationPrerequisite $articleMigrationPrerequisite,
    ) {}

    public function setOutput(OutputInterface $output): void
    {
        $this->output = $output;
    }

    public function getTitle(): string
    {
        return 'Turn tt_products article columns into attributes';
    }

    public function getDescription(): string
    {
        return 'Creates an attribute for every tt_products_articles column named in the extension configuration '
            . 'migration.articleAttributeColumns (e.g. a material) and links the migrated articles to their values.';
    }

    public function updateNecessary(): bool
    {
        foreach ($this->assignments() as [$articleUid, $column, , $value]) {
            $valueUid = $this->findValue($this->findAttribute($column), $value);
            if ($valueUid === null || !$this->isLinked($articleUid, $valueUid)) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        if (!$this->articleMigrationPrerequisite->isFulfilled()) {
            $this->output->writeln(
                '<error>tt_products_articles still has unmigrated rows; run the article migration '
                    . 'wizard (products_ttProductsArticleMigration) first.</error>'
            );
            return false;
        }
        foreach ($this->assignments() as [$articleUid, $column, $title, $value]) {
            $pid = $this->articlePid($articleUid);
            $valueUid = $this->value($this->attribute($column, $title, $pid), $value, $pid);
            if (!$this->isLinked($articleUid, $valueUid)) {
                $this->connectionPool->getConnectionForTable(self::MM_TABLE)->insert(self::MM_TABLE, [
                    'uid_local' => $articleUid,
                    'uid_foreign' => $valueUid,
                    'sorting' => 1,
                ]);
            }
        }
        return true;
    }

    /**
     * @return string[]
     */
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class, ArticleMigrationPrerequisite::class];
    }

    /**
     * Every article - value pair the configured columns call for, in column and article order.
     *
     * @return array<int, array{0: int, 1: string, 2: string, 3: string}> [article uid, column, attribute title, value]
     */
    private function assignments(): array
    {
        $assignments = [];
        foreach ($this->existingColumns() as $column => $title) {
            foreach ($this->legacyValues($column) as $legacyUid => $value) {
                $articleUid = $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, $legacyUid, self::ARTICLE_TABLE);
                if ($articleUid !== null && $articleUid > 0) {
                    $assignments[] = [$articleUid, $column, $title, $value];
                }
            }
        }
        return $assignments;
    }

    /**
     * @return array<string, string> configured column => attribute title, for the columns the legacy table has
     */
    private function existingColumns(): array
    {
        $configured = $this->migrationConfiguration->getArticleAttributeColumns();
        if ($configured === [] || !$this->migrationHelper->tablesExist(self::LEGACY_TABLE)) {
            return [];
        }
        $table = $this->connectionPool->getConnectionForTable(self::LEGACY_TABLE)->createSchemaManager()->introspectTable(self::LEGACY_TABLE);
        $columns = [];
        foreach ($configured as $column => $title) {
            if ($table->hasColumn($column)) {
                $columns[$column] = $title;
            } elseif (isset($this->output)) {
                $this->output->writeln(sprintf('<comment>%s has no column "%s"; it is left out.</comment>', self::LEGACY_TABLE, $column));
            }
        }
        return $columns;
    }

    /**
     * @return array<int, string> legacy uid => trimmed value, rows without a value left out
     */
    private function legacyValues(string $column): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::LEGACY_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid', $column)
            ->from(self::LEGACY_TABLE)
            ->where($queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)))
            ->orderBy('uid')
            ->executeQuery();
        $values = [];
        while ($row = $result->fetchAssociative()) {
            $value = trim((string)($row[$column] ?? ''));
            if ($value !== '') {
                $values[(int)$row['uid']] = $value;
            }
        }
        return $values;
    }

    private function findAttribute(string $column): ?int
    {
        return $this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE . '.' . $column, 0, self::ATTRIBUTE_TABLE);
    }

    /**
     * The attribute of a column, created on first use.
     */
    private function attribute(string $column, string $title, int $pid): int
    {
        $attributeUid = $this->findAttribute($column);
        if ($attributeUid !== null) {
            return $attributeUid;
        }
        $connection = $this->connectionPool->getConnectionForTable(self::ATTRIBUTE_TABLE);
        $connection->insert(self::ATTRIBUTE_TABLE, ['pid' => $pid, 'title' => $title]);
        $attributeUid = (int)$connection->lastInsertId();
        $this->migrationHelper->recordMapping(self::LEGACY_TABLE . '.' . $column, 0, self::ATTRIBUTE_TABLE, $attributeUid);
        return $attributeUid;
    }

    private function findValue(?int $attributeUid, string $value): ?int
    {
        if ($attributeUid === null) {
            return null;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::VALUE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        $uid = $queryBuilder->select('uid')
            ->from(self::VALUE_TABLE)
            ->where(
                $queryBuilder->expr()->eq('attribute', $queryBuilder->createNamedParameter($attributeUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('value', $queryBuilder->createNamedParameter($value)),
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
        return $uid !== false ? (int)$uid : null;
    }

    /**
     * The value of an attribute, created on first use.
     */
    private function value(int $attributeUid, string $value, int $pid): int
    {
        $valueUid = $this->findValue($attributeUid, $value);
        if ($valueUid !== null) {
            return $valueUid;
        }
        $connection = $this->connectionPool->getConnectionForTable(self::VALUE_TABLE);
        $connection->insert(self::VALUE_TABLE, ['pid' => $pid, 'attribute' => $attributeUid, 'value' => $value]);
        return (int)$connection->lastInsertId();
    }

    private function isLinked(int $articleUid, int $valueUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::MM_TABLE);
        return (int)$queryBuilder->count('uid_local')
            ->from(self::MM_TABLE)
            ->where(
                $queryBuilder->expr()->eq('uid_local', $queryBuilder->createNamedParameter($articleUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($valueUid, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }

    private function articlePid(int $articleUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::ARTICLE_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('pid')
            ->from(self::ARTICLE_TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($articleUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
