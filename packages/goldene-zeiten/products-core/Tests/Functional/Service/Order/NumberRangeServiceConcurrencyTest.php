<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Service\Order\NumberRangeService;
use GoldeneZeiten\Products\Core\Tests\Functional\Fixtures\Database\ConcurrentNumberRangeSeedMiddleware;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The first number of a scope has no row to increment yet, so it is inserted. Two requests drawing that
 * first number at once both find no row and both insert; the second insert hits the primary key. It must
 * then draw the next number from the row the first one created rather than fail the checkout.
 */
final class NumberRangeServiceConcurrencyTest extends AbstractFunctionalTestCase
{
    protected array $configurationToUseInTestInstance = [
        'DB' => [
            'globalDriverMiddlewares' => [
                'products-core-tests/concurrent-number-range-seed' => [
                    'target' => ConcurrentNumberRangeSeedMiddleware::class,
                    'after' => [
                        'typo3/core/custom-platform-driver-middleware',
                        'typo3/core/custom-pdo-driver-result-middleware',
                    ],
                ],
            ],
        ],
    ];

    protected function tearDown(): void
    {
        ConcurrentNumberRangeSeedMiddleware::$competingScope = null;
        parent::tearDown();
    }

    #[Test]
    public function aScopeSeededConcurrentlyContinuesFromTheCompetitorsNumber(): void
    {
        ConcurrentNumberRangeSeedMiddleware::$competingScope = 'order';
        $subject = $this->get(NumberRangeService::class);

        $this->assertSame(6, $subject->next('order'));
    }

    /**
     * PostgreSQL aborts the whole transaction on a failed statement; the losing insert must not take the
     * surrounding placement transaction down with it.
     */
    #[Test]
    public function aScopeSeededConcurrentlyLeavesTheSurroundingTransactionUsable(): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_products_number_range');
        $connection->beginTransaction();
        ConcurrentNumberRangeSeedMiddleware::$competingScope = 'invoice';
        $subject = $this->get(NumberRangeService::class);

        $subject->next('invoice');
        $next = $subject->next('invoice');
        $connection->commit();

        $this->assertSame(7, $next);
    }
}
