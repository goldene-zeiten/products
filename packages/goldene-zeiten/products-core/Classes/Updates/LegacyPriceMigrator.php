<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Migrates the prices of a `tt_products` or `tt_products_articles` row according to how the legacy shop
 * used `price2` ({@see LegacyPrice2Usage}). Call {@see prepare()} once before migrating a table; the
 * other methods assume it returned true.
 */
final class LegacyPriceMigrator
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly LegacyMigrationConfiguration $migrationConfiguration,
    ) {}

    /**
     * False, with the reason written to the output, while the table carries a price2 whose usage is not
     * configured in a usable way.
     */
    public function prepare(string $legacyTable, OutputInterface $output): bool
    {
        $rowsWithPrice2 = $this->countRowsWithPrice2($legacyTable);
        if ($rowsWithPrice2 === 0) {
            return true;
        }
        $usage = $this->usage();
        if ($usage === LegacyPrice2Usage::Undecided || ($usage === LegacyPrice2Usage::Reseller && $this->resellerGroup() === 0)) {
            $output->writeln(sprintf(
                '<error>%d %s rows have a price2. Declare how the legacy shop used it in the extension '
                    . 'configuration EXTENSIONS/products_core/migration/legacyPrice2 (and legacyPrice2FeGroup '
                    . 'for a reseller price) before migrating.</error>',
                $rowsWithPrice2,
                $legacyTable
            ));
            return false;
        }
        if ($usage === LegacyPrice2Usage::Unused) {
            $output->writeln(sprintf('<comment>%d %s rows have a price2, ignored as configured.</comment>', $rowsWithPrice2, $legacyTable));
        }
        return true;
    }

    /**
     * @param array<string, mixed> $legacyRow
     */
    public function price(array $legacyRow): string
    {
        $price2 = (float)($legacyRow['price2'] ?? 0);
        $price = $this->usage() === LegacyPrice2Usage::Selling && $price2 > 0 ? $price2 : (float)$legacyRow['price'];
        return $this->format($price);
    }

    /**
     * Adds the reseller price period of a migrated product or article, if the row has one.
     *
     * @param array<string, mixed> $legacyRow
     * @param 'product'|'article' $parentField
     */
    public function migrateResellerPrice(array $legacyRow, string $parentField, int $localUid, int $pid): void
    {
        $price2 = (float)($legacyRow['price2'] ?? 0);
        if ($price2 <= 0 || $this->usage() !== LegacyPrice2Usage::Reseller) {
            return;
        }
        $this->connectionPool->getConnectionForTable('tx_products_domain_model_priceperiod')->insert(
            'tx_products_domain_model_priceperiod',
            [
                'pid' => $pid,
                $parentField => $localUid,
                'fe_group' => $this->resellerGroup(),
                'price' => $this->format($price2),
                'sorting' => 1,
            ]
        );
        $localTable = 'tx_products_domain_model_' . $parentField;
        $this->connectionPool->getConnectionForTable($localTable)->update(
            $localTable,
            [
                'price_periods' => 1,
            ],
            [
                'uid' => $localUid,
            ]
        );
    }

    private function countRowsWithPrice2(string $legacyTable): int
    {
        $connection = $this->connectionPool->getConnectionForTable($legacyTable);
        if (!$connection->createSchemaManager()->introspectTable($legacyTable)->hasColumn('price2')) {
            return 0;
        }
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')
            ->from($legacyTable)
            ->where(
                $queryBuilder->expr()->eq('deleted', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('price2', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne();
    }

    private function usage(): LegacyPrice2Usage
    {
        return LegacyPrice2Usage::tryFrom($this->migrationConfiguration->getString('legacyPrice2'))
            ?? LegacyPrice2Usage::Undecided;
    }

    private function resellerGroup(): int
    {
        return max(0, $this->migrationConfiguration->getInt('legacyPrice2FeGroup'));
    }

    private function format(float $price): string
    {
        return number_format($price, 2, '.', '');
    }
}
