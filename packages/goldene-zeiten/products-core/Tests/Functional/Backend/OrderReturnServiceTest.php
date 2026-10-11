<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\Exception\OrderAccessDeniedException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderNotAwaitingReturnException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderNotShippedException;
use GoldeneZeiten\Products\Core\Backend\OrderReturnService;
use GoldeneZeiten\Products\Core\Backend\OrderStatusWriter;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * The backend steps after a customer withdrew from a shipped order: record the delivery that starts the
 * withdrawal period, the proof of return shipment that releases the refund, and the receipt of the
 * returned goods that puts them back into stock.
 */
final class OrderReturnServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderReturnServiceTest/orders.csv');
    }

    #[Test]
    public function markDeliveredStampsTheDeliveryOfAShippedOrder(): void
    {
        $subject = $this->get(OrderReturnService::class);

        $subject->markDelivered(1, $this->backendUser(1));

        $this->assertGreaterThan(0, $this->orderColumn(1, 'delivered_at'));
        $this->assertSame('shipped', $this->orderStatus(1));
    }

    #[Test]
    public function markDeliveredRefusesAnOrderThatWasNotShipped(): void
    {
        $subject = $this->get(OrderReturnService::class);
        $this->expectException(OrderNotShippedException::class);
        $this->expectExceptionCode(1791730325);

        $subject->markDelivered(4, $this->backendUser(1));
    }

    #[Test]
    public function markDeliveredRefusesAUserWhoMayOnlyRead(): void
    {
        $subject = $this->get(OrderReturnService::class);
        $this->expectException(OrderAccessDeniedException::class);
        $this->expectExceptionCode(1791730322);

        $subject->markDelivered(1, $this->backendUser(2));
    }

    #[Test]
    public function completingAShippedOrderRecordsItsDelivery(): void
    {
        $subject = $this->get(OrderStatusWriter::class);

        $subject->changeStatus(1, OrderStatus::COMPLETED, null, $this->backendUser(1));

        $this->assertGreaterThan(0, $this->orderColumn(1, 'delivered_at'));
    }

    #[Test]
    public function recordReturnProofStampsAWithdrawnOrder(): void
    {
        $subject = $this->get(OrderReturnService::class);

        $subject->recordReturnProof(2, $this->backendUser(1));

        $this->assertGreaterThan(0, $this->orderColumn(2, 'return_proof_at'));
        $this->assertSame('withdrawn', $this->orderStatus(2));
    }

    #[Test]
    public function recordReturnProofRefusesAnOrderThatIsNotAwaitingReturn(): void
    {
        $subject = $this->get(OrderReturnService::class);
        $this->expectException(OrderNotAwaitingReturnException::class);
        $this->expectExceptionCode(1791730327);

        $subject->recordReturnProof(1, $this->backendUser(1));
    }

    #[Test]
    public function markReturnedCancelsTheOrderAndRestocksEverythingWhenAllItemsWereWithdrawn(): void
    {
        $subject = $this->get(OrderReturnService::class);

        $subject->markReturned(2, $this->backendUser(1));

        $this->assertSame('cancelled', $this->orderStatus(2));
        $this->assertGreaterThan(0, $this->orderColumn(2, 'returned_at'));
        $this->assertSame(11, $this->stock(1));
        $this->assertSame(12, $this->stock(2));
    }

    #[Test]
    public function markReturnedCompletesTheOrderAndRestocksOnlyTheWithdrawnItemsOfAPartialWithdrawal(): void
    {
        $subject = $this->get(OrderReturnService::class);

        $subject->markReturned(3, $this->backendUser(1));

        $this->assertSame('completed', $this->orderStatus(3));
        $this->assertSame(11, $this->stock(1));
        $this->assertSame(10, $this->stock(2));
    }

    #[Test]
    public function markReturnedCannotBeRepeated(): void
    {
        $subject = $this->get(OrderReturnService::class);
        $subject->markReturned(2, $this->backendUser(1));
        $this->expectException(OrderNotAwaitingReturnException::class);
        $this->expectExceptionCode(1791730327);

        $subject->markReturned(2, $this->backendUser(1));
    }

    private function backendUser(int $uid): BackendUserAuthentication
    {
        $backendUser = $this->setUpBackendUser($uid);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        return $backendUser;
    }

    private function orderStatus(int $orderUid): string
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_order');
        return (string)$queryBuilder->select('status')
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function orderColumn(int $orderUid, string $column): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_order');
        return (int)$queryBuilder->select($column)
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function stock(int $productUid): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select('in_stock')
            ->from('tx_products_domain_model_product')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($productUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
