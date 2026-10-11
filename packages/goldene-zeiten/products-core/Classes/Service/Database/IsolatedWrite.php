<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Database;

use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Runs a write whose failure must not take down the surrounding work - a side effect of placing an order,
 * an insert that may lose a race. Inside an open transaction the write gets a savepoint of its own: a
 * failing statement aborts the whole transaction on PostgreSQL, so merely catching the exception would
 * still lose everything around it. Outside a transaction it runs in a transaction of its own.
 */
final class IsolatedWrite
{
    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * @param string $table a table the write goes to, selecting its connection
     * @param callable(): mixed $write its result is discarded
     * @return \Throwable|null what made the write fail; it has been rolled back
     */
    public function run(string $table, callable $write): ?\Throwable
    {
        $connection = $this->connectionPool->getConnectionForTable($table);
        $connection->beginTransaction();
        try {
            $write();
            $connection->commit();
            return null;
        } catch (\Throwable $exception) {
            $connection->rollBack();
            return $exception;
        }
    }
}
