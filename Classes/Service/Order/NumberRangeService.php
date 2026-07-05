<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Race-safe sequence generator backed by tx_products_number_range.
 *
 * SELECT ... FOR UPDATE is MySQL/Postgres-only syntax and unsupported by sqlite,
 * which this extension must also run on. An atomic "UPDATE ... SET x = x + 1"
 * takes the same row lock on transactional platforms and is portable everywhere.
 */
final class NumberRangeService
{
    private const TABLE = 'tx_products_number_range';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    public function next(string $scope): int
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->beginTransaction();
        try {
            $next = $this->incrementOrCreate($scope);
            $connection->commit();
            return $next;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }

    private function incrementOrCreate(string $scope): int
    {
        $updateQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $affectedRows = $updateQueryBuilder
            ->update(self::TABLE)
            ->set('current_value', $updateQueryBuilder->quoteIdentifier('current_value') . ' + 1', false)
            ->where($updateQueryBuilder->expr()->eq('scope', $updateQueryBuilder->createNamedParameter($scope)))
            ->executeStatement();

        if ($affectedRows === 0) {
            $insertQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
            $insertQueryBuilder->insert(self::TABLE)
                ->values(['scope' => $scope, 'current_value' => 1])
                ->executeStatement();
            return 1;
        }

        return $this->fetchCurrentValue($scope);
    }

    private function fetchCurrentValue(string $scope): int
    {
        $selectQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $currentValue = $selectQueryBuilder
            ->select('current_value')
            ->from(self::TABLE)
            ->where($selectQueryBuilder->expr()->eq('scope', $selectQueryBuilder->createNamedParameter($scope)))
            ->executeQuery()
            ->fetchOne();

        return (int)$currentValue;
    }
}
