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
    public function fetchRowMapsShippingFields(): void
    {
        $row = $this->subject->fetchRow(1);

        self::assertNotNull($row);
        self::assertSame(1, $row['shippingMethodUid']);
        self::assertSame(500, $row['shippingTotalCents']);
    }

    #[Test]
    public function fetchRowMapsAbsentShippingAsZero(): void
    {
        $row = $this->subject->fetchRow(2);

        self::assertNotNull($row);
        self::assertSame(0, $row['shippingMethodUid']);
        self::assertSame(0, $row['shippingTotalCents']);
    }

    #[Test]
    public function fetchVoucherRedemptionsReturnsRowsForThatOrder(): void
    {
        $redemptions = $this->subject->fetchVoucherRedemptions(1);

        self::assertCount(1, $redemptions);
        self::assertSame('SAVE10', $redemptions[0]['voucherCode']);
        self::assertSame(199, $redemptions[0]['discountTotalCents']);
    }

    #[Test]
    public function fetchVoucherRedemptionsIsEmptyForAnOrderWithNone(): void
    {
        self::assertSame([], $this->subject->fetchVoucherRedemptions(2));
    }

    #[Test]
    public function fetchGainedVoucherReturnsTheGeneratedCode(): void
    {
        $gainedVoucher = $this->subject->fetchGainedVoucher(1);

        self::assertNotNull($gainedVoucher);
        self::assertSame('GAINED-ABC123', $gainedVoucher['code']);
        self::assertFalse($gainedVoucher['used']);
    }

    #[Test]
    public function fetchGainedVoucherIsNullWhenTheOrderGeneratedNone(): void
    {
        self::assertNull($this->subject->fetchGainedVoucher(2));
    }

    #[Test]
    public function fetchCreditPointsLedgerReturnsRowsForThatOrder(): void
    {
        $ledger = $this->subject->fetchCreditPointsLedger(1);

        self::assertCount(1, $ledger);
        self::assertSame(5, $ledger[0]['frontendUser']);
        self::assertSame(20, $ledger[0]['points']);
        self::assertSame('earn', $ledger[0]['type']);
    }

    #[Test]
    public function fetchCreditPointsLedgerIsEmptyForAnOrderWithNone(): void
    {
        self::assertSame([], $this->subject->fetchCreditPointsLedger(2));
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

    /**
     * Regression test: fetching a real Extbase entity (not just the raw QueryBuilder row) for an
     * order with a non-zero discount_total/shipping_total used to crash with "no such table:
     * tx_products_domain_valueobject_money" - Extbase's reflection-based property typing fell back
     * to the Money-typed setter parameter instead of the property's own native int type whenever a
     * property lacked an explicit `@var int` docblock. Fixed by adding that docblock to
     * Order::$discountTotal/$shippingTotal (matching the existing $totalNet/$totalTax/$totalGross
     * pattern). This is exactly the code path the "mark paid"/"refund" backend actions exercise for
     * any real order that has a discount or shipping cost.
     */
    #[Test]
    public function findForEditingHydratesAnOrderWithNonZeroMoneyBackedFieldsWithoutCrashing(): void
    {
        $order = $this->subject->findForEditing(1);

        self::assertInstanceOf(Order::class, $order);
        self::assertSame(1999, $order->getTotalGross()->getCents());
        self::assertSame(500, $order->getDiscountTotal()->getCents());
        self::assertSame(500, $order->getShippingTotal()->getCents());
    }
}
