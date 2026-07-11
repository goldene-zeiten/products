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

        $this->assertCount(2, $orders);
    }

    #[Test]
    public function fetchFilteredByStatus(): void
    {
        $orders = $this->subject->fetchFiltered(new OrderListFilter(status: 'confirmed'));

        $this->assertCount(1, $orders);
        $this->assertSame('ORD-2', $orders[0]['orderNumber']);
    }

    #[Test]
    public function fetchFilteredByEmail(): void
    {
        $orders = $this->subject->fetchFiltered(new OrderListFilter(email: 'alice'));

        $this->assertCount(1, $orders);
        $this->assertSame('ORD-1', $orders[0]['orderNumber']);
    }

    #[Test]
    public function fetchRowMapsFields(): void
    {
        $row = $this->subject->fetchRow(1);

        $this->assertNotNull($row);
        $this->assertSame('ORD-1', $row['orderNumber']);
        $this->assertSame('alice@example.com', $row['email']);
        $this->assertSame(1999, $row['totalGrossCents']);
    }

    #[Test]
    public function fetchRowReturnsNullForDeletedOrder(): void
    {
        $this->assertNull($this->subject->fetchRow(3));
    }

    #[Test]
    public function fetchRowMapsShippingFields(): void
    {
        $row = $this->subject->fetchRow(1);

        $this->assertNotNull($row);
        $this->assertSame(1, $row['shippingMethodUid']);
        $this->assertSame(500, $row['shippingTotalCents']);
    }

    #[Test]
    public function fetchRowMapsAbsentShippingAsZero(): void
    {
        $row = $this->subject->fetchRow(2);

        $this->assertNotNull($row);
        $this->assertSame(0, $row['shippingMethodUid']);
        $this->assertSame(0, $row['shippingTotalCents']);
    }

    #[Test]
    public function fetchVoucherRedemptionsReturnsRowsForThatOrder(): void
    {
        $redemptions = $this->subject->fetchVoucherRedemptions(1);

        $this->assertCount(1, $redemptions);
        $this->assertSame('SAVE10', $redemptions[0]['voucherCode']);
        $this->assertSame(199, $redemptions[0]['discountTotalCents']);
    }

    #[Test]
    public function fetchVoucherRedemptionsIsEmptyForAnOrderWithNone(): void
    {
        $this->assertSame([], $this->subject->fetchVoucherRedemptions(2));
    }

    #[Test]
    public function fetchGainedVoucherReturnsTheGeneratedCode(): void
    {
        $gainedVoucher = $this->subject->fetchGainedVoucher(1);

        $this->assertNotNull($gainedVoucher);
        $this->assertSame('GAINED-ABC123', $gainedVoucher['code']);
        $this->assertFalse($gainedVoucher['used']);
    }

    #[Test]
    public function fetchGainedVoucherIsNullWhenTheOrderGeneratedNone(): void
    {
        $this->assertNull($this->subject->fetchGainedVoucher(2));
    }

    #[Test]
    public function fetchCreditPointsLedgerReturnsRowsForThatOrder(): void
    {
        $ledger = $this->subject->fetchCreditPointsLedger(1);

        $this->assertCount(1, $ledger);
        $this->assertSame(5, $ledger[0]['frontendUser']);
        $this->assertSame(20, $ledger[0]['points']);
        $this->assertSame('earn', $ledger[0]['type']);
    }

    #[Test]
    public function fetchCreditPointsLedgerIsEmptyForAnOrderWithNone(): void
    {
        $this->assertSame([], $this->subject->fetchCreditPointsLedger(2));
    }

    #[Test]
    public function findForEditingAndPersistWritesTheTransitionToTheDatabase(): void
    {
        $order = $this->subject->findForEditing(1);
        $this->assertInstanceOf(Order::class, $order);

        $this->get(OrderStatusManager::class)->transitionPayment($order, PaymentStatus::PAID);
        $this->subject->persist($order);

        $row = $this->subject->fetchRow(1);
        $this->assertNotNull($row);
        $this->assertSame('paid', $row['paymentStatus']);
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

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(1999, $order->getTotalGross()->getCents());
        $this->assertSame(500, $order->getDiscountTotal()->getCents());
        $this->assertSame(500, $order->getShippingTotal()->getCents());
    }
}
