<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Race-safe sequence generator: atomic UPDATE instead of SELECT FOR UPDATE for sqlite compatibility.
 * Safe to call inside a surrounding transaction.
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

    /**
     * A scope's first number has no row to increment. Two requests drawing it at once both insert one; the
     * loser increments the winner's row instead.
     */
    private function incrementOrCreate(string $scope): int
    {
        if ($this->increment($scope)) {
            return $this->fetchCurrentValue($scope);
        }
        if ($this->seed($scope)) {
            return 1;
        }
        $this->increment($scope);

        return $this->fetchCurrentValue($scope);
    }

    private function increment(string $scope): bool
    {
        $updateQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $affectedRows = $updateQueryBuilder
            ->update(self::TABLE)
            ->set('current_value', $updateQueryBuilder->quoteIdentifier('current_value') . ' + 1', false)
            ->where($updateQueryBuilder->expr()->eq('scope', $updateQueryBuilder->createNamedParameter($scope)))
            ->executeStatement();

        return $affectedRows > 0;
    }

    /**
     * Runs in a nested transaction - a savepoint - so a losing insert does not abort the surrounding
     * transaction, as PostgreSQL does on any failed statement.
     *
     * @return bool false when another request seeded the scope first
     */
    private function seed(string $scope): bool
    {
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $connection->beginTransaction();
        try {
            $insertQueryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
            $insertQueryBuilder->insert(self::TABLE)
                ->values(['scope' => $scope, 'current_value' => 1])
                ->executeStatement();
            $connection->commit();
            return true;
        } catch (UniqueConstraintViolationException) {
            $connection->rollBack();
            return false;
        }
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
