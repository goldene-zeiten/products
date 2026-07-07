<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Backend;

use GoldeneZeiten\Products\Backend\OrderListFilter;
use GoldeneZeiten\Products\Backend\OrderManagementRepository;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Service\Order\OrderStatusManager;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class OrderManagementRepositoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private OrderManagementRepository $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/orders_for_management.csv');
        $this->subject = $this->get(OrderManagementRepository::class);
    }

    #[Test]
    public function fetchFilteredExcludesDeletedOrders(): void
    {
        $orders = $this->subject->fetchFiltered(new OrderListFilter());

        self::assertCount(2, $orders);
    }

    #[Test]
    public function fetchFilteredByStatus(): void
    {
        $orders = $this->subject->fetchFiltered(new OrderListFilter(status: 'confirmed'));

        self::assertCount(1, $orders);
        self::assertSame('ORD-2', $orders[0]['orderNumber']);
    }

    #[Test]
    public function fetchFilteredByEmail(): void
    {
        $orders = $this->subject->fetchFiltered(new OrderListFilter(email: 'alice'));

        self::assertCount(1, $orders);
        self::assertSame('ORD-1', $orders[0]['orderNumber']);
    }

    #[Test]
    public function fetchRowMapsFields(): void
    {
        $row = $this->subject->fetchRow(1);

        self::assertNotNull($row);
        self::assertSame('ORD-1', $row['orderNumber']);
        self::assertSame('alice@example.com', $row['email']);
        self::assertSame(1999, $row['totalGrossCents']);
    }

    #[Test]
    public function fetchRowReturnsNullForDeletedOrder(): void
    {
        self::assertNull($this->subject->fetchRow(3));
    }

    #[Test]
    public function findForEditingAndPersistWritesTheTransitionToTheDatabase(): void
    {
        $order = $this->subject->findForEditing(1);
        self::assertInstanceOf(Order::class, $order);

        $this->get(OrderStatusManager::class)->transitionPayment($order, PaymentStatus::PAID);
        $this->subject->persist($order);

        $row = $this->subject->fetchRow(1);
        self::assertNotNull($row);
        self::assertSame('paid', $row['paymentStatus']);
    }
}
