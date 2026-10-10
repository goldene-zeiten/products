<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Placing an order takes its items out of stock. An order cancelled before it was shipped never leaves
 * the warehouse, so cancelling it must put those items back - otherwise every failed payment shrinks the
 * sellable stock for good.
 */
final class StockReleaseOnCancellationTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/StockReleaseOnCancellationTest/cancellable_order_with_stock.csv');
    }

    #[Test]
    public function cancellingAnUnshippedOrderPutsItsProductItemsBackIntoStock(): void
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertNotNull($order);
        $subject = $this->get(OrderStatusManager::class);

        $subject->transition($order, OrderStatus::CANCELLED);

        // Only the order's own line (2) returns; the other order's line on the same product stays out.
        $this->assertSame(7, $this->stock('tx_products_domain_model_product', 1));
    }

    #[Test]
    public function cancellingAnUnshippedOrderPutsItsArticleItemsBackIntoStock(): void
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertNotNull($order);
        $subject = $this->get(OrderStatusManager::class);

        $subject->transition($order, OrderStatus::CANCELLED);

        $this->assertSame(5, $this->stock('tx_products_domain_model_article', 1));
        $this->assertSame(0, $this->stock('tx_products_domain_model_product', 3));
    }

    #[Test]
    public function cancellingAnOrderLeavesUnlimitedStockUntouched(): void
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(1);
        $this->assertNotNull($order);
        $subject = $this->get(OrderStatusManager::class);

        $subject->transition($order, OrderStatus::CANCELLED);

        $this->assertSame(0, $this->stock('tx_products_domain_model_product', 2));
    }

    private function stock(string $table, int $uid): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('in_stock')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
