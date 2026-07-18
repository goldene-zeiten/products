<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\OrderStatusWriter;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Core\Event\PaymentStatusChangedEvent;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InvalidOrderStatusTransitionException;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InvalidPaymentStatusTransitionException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * Covers {@see OrderStatusWriter}: the status/payment write lands in the database, the status log is
 * appended, invalid transitions are rejected, and the status-changed events fire.
 */
final class OrderStatusWriterTest extends AbstractFunctionalTestCase
{
    private const TABLE = 'tx_products_domain_model_order';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderStatusWriterTest/orders.csv');
    }

    #[Test]
    public function changeStatusWritesTheStatusAndAppendsTheLogEntry(): void
    {
        $subject = $this->get(OrderStatusWriter::class);

        $subject->changeStatus(1, OrderStatus::CONFIRMED, 'Shipped early.', $this->backendUser());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/order_status_writer_status_changed.csv');
        $log = $this->fetchStatusLog(1);
        $this->assertCount(1, $log);
        $this->assertSame('new', $log[0]['from']);
        $this->assertSame('confirmed', $log[0]['to']);
        $this->assertSame('Shipped early.', $log[0]['note']);
        $this->assertArrayHasKey('at', $log[0]);
    }

    #[Test]
    public function changeStatusWithoutANoteOmitsItFromTheLogEntry(): void
    {
        $subject = $this->get(OrderStatusWriter::class);

        $subject->changeStatus(1, OrderStatus::CONFIRMED, null, $this->backendUser());

        $this->assertArrayNotHasKey('note', $this->fetchStatusLog(1)[0]);
    }

    #[Test]
    public function changeStatusRejectsAnInvalidTransition(): void
    {
        $subject = $this->get(OrderStatusWriter::class);

        $this->expectException(InvalidOrderStatusTransitionException::class);
        $this->expectExceptionCode(1752710401);

        $subject->changeStatus(1, OrderStatus::COMPLETED, null, $this->backendUser());
    }

    #[Test]
    public function changeStatusDispatchesTheUidBasedEvent(): void
    {
        $dispatcher = $this->recordingDispatcher();
        $subject = new OrderStatusWriter($this->get(ConnectionPool::class), $dispatcher);

        $subject->changeStatus(1, OrderStatus::CONFIRMED, null, $this->backendUser());

        $captured = $dispatcher->events[0] ?? null;
        $this->assertInstanceOf(OrderStatusChangedEvent::class, $captured);
        $this->assertSame(1, $captured->getOrderUid());
        $this->assertSame(OrderStatus::NEW, $captured->getPreviousStatus());
        $this->assertSame(OrderStatus::CONFIRMED, $captured->getNewStatus());
    }

    #[Test]
    public function changePaymentStatusWritesThePaymentStatus(): void
    {
        $subject = $this->get(OrderStatusWriter::class);

        $subject->changePaymentStatus(1, PaymentStatus::PAID, $this->backendUser());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/order_status_writer_payment_changed.csv');
    }

    #[Test]
    public function changePaymentStatusRejectsAnInvalidTransition(): void
    {
        $subject = $this->get(OrderStatusWriter::class);

        $this->expectException(InvalidPaymentStatusTransitionException::class);
        $this->expectExceptionCode(1752710402);

        $subject->changePaymentStatus(1, PaymentStatus::REFUNDED, $this->backendUser());
    }

    #[Test]
    public function changePaymentStatusDispatchesTheUidBasedEvent(): void
    {
        $dispatcher = $this->recordingDispatcher();
        $subject = new OrderStatusWriter($this->get(ConnectionPool::class), $dispatcher);

        $subject->changePaymentStatus(1, PaymentStatus::PAID, $this->backendUser());

        $captured = $dispatcher->events[0] ?? null;
        $this->assertInstanceOf(PaymentStatusChangedEvent::class, $captured);
        $this->assertSame(1, $captured->getOrderUid());
        $this->assertSame(PaymentStatus::OPEN, $captured->getPreviousStatus());
        $this->assertSame(PaymentStatus::PAID, $captured->getNewStatus());
    }

    private function backendUser(): BackendUserAuthentication
    {
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
        return $backendUser;
    }

    /**
     * @return EventDispatcherInterface&object{events: array<int, object>}
     */
    private function recordingDispatcher(): EventDispatcherInterface
    {
        return new class () implements EventDispatcherInterface {
            /**
             * @var array<int, object>
             */
            public array $events = [];

            public function dispatch(object $event): object
            {
                $this->events[] = $event;
                return $event;
            }
        };
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchStatusLog(int $uid): array
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable(self::TABLE);
        $json = (string)$connection->fetchOne(
            'SELECT status_log FROM ' . self::TABLE . ' WHERE uid = ?',
            [$uid]
        );
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
}
