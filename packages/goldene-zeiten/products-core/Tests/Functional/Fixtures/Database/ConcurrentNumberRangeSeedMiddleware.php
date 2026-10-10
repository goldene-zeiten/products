<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\Database;

use Doctrine\DBAL\Driver;
use Doctrine\DBAL\Driver\Connection as DriverConnection;
use Doctrine\DBAL\Driver\Middleware;
use Doctrine\DBAL\Driver\Middleware\AbstractConnectionMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractDriverMiddleware;
use Doctrine\DBAL\Driver\Middleware\AbstractStatementMiddleware;
use Doctrine\DBAL\Driver\Result;
use Doctrine\DBAL\Driver\Statement;

/**
 * Simulates a second request seeding a number-range scope in the instant between this request finding no
 * row for it and inserting the first one: when armed, the next update of the number-range table is
 * followed by the competitor's own insert of the scope (at value 5), before this request gets to insert.
 */
final class ConcurrentNumberRangeSeedMiddleware implements Middleware
{
    public static ?string $competingScope = null;

    public function wrap(Driver $driver): Driver
    {
        return new class ($driver) extends AbstractDriverMiddleware {
            public function connect(array $params): DriverConnection
            {
                return new class (parent::connect($params)) extends AbstractConnectionMiddleware {
                    public function prepare(string $sql): Statement
                    {
                        $statement = parent::prepare($sql);
                        $scope = ConcurrentNumberRangeSeedMiddleware::$competingScope;
                        if ($scope === null || !str_starts_with(ltrim($sql), 'UPDATE') || !str_contains($sql, 'tx_products_number_range')) {
                            return $statement;
                        }
                        ConcurrentNumberRangeSeedMiddleware::$competingScope = null;

                        return new class ($statement, $this, $scope) extends AbstractStatementMiddleware {
                            public function __construct(
                                Statement $wrappedStatement,
                                private readonly DriverConnection $connection,
                                private readonly string $scope
                            ) {
                                parent::__construct($wrappedStatement);
                            }

                            public function execute(): Result
                            {
                                $result = parent::execute();
                                $this->connection->exec(sprintf(
                                    "INSERT INTO tx_products_number_range (scope, current_value) VALUES ('%s', 5)",
                                    $this->scope
                                ));

                                return $result;
                            }
                        };
                    }
                };
            }
        };
    }
}
