<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Backend;

use GoldeneZeiten\Products\Backend\OrderListFilter;
use GoldeneZeiten\Products\Backend\OrderManagementRepository;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Service\Order\OrderStatusManager;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class OrderManagementRepositoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderManagementRepositoryTest/orders_for_management.csv');
    }

    #[Test]
    #[DataProvider('fetchFilteredProvider')]
    public function fetchFilteredAppliesTheGivenFilter(OrderListFilter $filter, int $expectedCount, ?string $expectedFirstOrderNumber): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $orders = $subject->fetchFiltered($filter);

        $this->assertCount($expectedCount, $orders);
        if ($expectedFirstOrderNumber !== null) {
            $this->assertSame($expectedFirstOrderNumber, $orders[0]['orderNumber']);
        }
    }

    public static function fetchFilteredProvider(): \Generator
    {
        yield 'excludes deleted orders' => [
            'filter' => new OrderListFilter(),
            'expectedCount' => 2,
            'expectedFirstOrderNumber' => null,
        ];

        yield 'filters by status' => [
            'filter' => new OrderListFilter(status: 'confirmed'),
            'expectedCount' => 1,
            'expectedFirstOrderNumber' => 'ORD-2',
        ];

        yield 'filters by email' => [
            'filter' => new OrderListFilter(email: 'alice'),
            'expectedCount' => 1,
            'expectedFirstOrderNumber' => 'ORD-1',
        ];
    }

    #[Test]
    public function fetchRowMapsFields(): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $row = $subject->fetchRow(1);

        $this->assertNotNull($row);
        $this->assertSame('ORD-1', $row['orderNumber']);
        $this->assertSame('alice@example.com', $row['email']);
        $this->assertSame(1999, $row['totalGrossCents']);
    }

    #[Test]
    public function fetchRowReturnsNullForDeletedOrder(): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $this->assertNull($subject->fetchRow(3));
    }

    #[Test]
    #[DataProvider('fetchRowShippingFieldsProvider')]
    public function fetchRowMapsShippingFields(int $orderUid, string $expectedShippingLabel, int $expectedShippingTotalCents): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $row = $subject->fetchRow($orderUid);

        $this->assertNotNull($row);
        $this->assertSame($expectedShippingLabel, $row['shippingLabel']);
        $this->assertSame($expectedShippingTotalCents, $row['shippingTotalCents']);
    }

    public static function fetchRowShippingFieldsProvider(): \Generator
    {
        yield 'shipping fields are mapped' => [
            'orderUid' => 1,
            'expectedShippingLabel' => 'Standard',
            'expectedShippingTotalCents' => 500,
        ];

        yield 'absent shipping is mapped as empty' => [
            'orderUid' => 2,
            'expectedShippingLabel' => '',
            'expectedShippingTotalCents' => 0,
        ];
    }

    #[Test]
    #[DataProvider('fetchVoucherRedemptionsProvider')]
    public function fetchVoucherRedemptions(int $orderUid, ?string $expectedVoucherCode, ?int $expectedDiscountTotalCents): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $redemptions = $subject->fetchVoucherRedemptions($orderUid);

        if ($expectedVoucherCode === null) {
            $this->assertSame([], $redemptions);
            return;
        }
        $this->assertCount(1, $redemptions);
        $this->assertSame($expectedVoucherCode, $redemptions[0]['voucherCode']);
        $this->assertSame($expectedDiscountTotalCents, $redemptions[0]['discountTotalCents']);
    }

    public static function fetchVoucherRedemptionsProvider(): \Generator
    {
        yield 'returns rows for that order' => [
            'orderUid' => 1,
            'expectedVoucherCode' => 'SAVE10',
            'expectedDiscountTotalCents' => 199,
        ];

        yield 'is empty for an order with none' => [
            'orderUid' => 2,
            'expectedVoucherCode' => null,
            'expectedDiscountTotalCents' => null,
        ];
    }

    #[Test]
    #[DataProvider('fetchGainedVoucherProvider')]
    public function fetchGainedVoucher(int $orderUid, ?string $expectedCode): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $gainedVoucher = $subject->fetchGainedVoucher($orderUid);

        if ($expectedCode === null) {
            $this->assertNull($gainedVoucher);
            return;
        }
        $this->assertNotNull($gainedVoucher);
        $this->assertSame($expectedCode, $gainedVoucher['code']);
        $this->assertFalse($gainedVoucher['used']);
    }

    public static function fetchGainedVoucherProvider(): \Generator
    {
        yield 'returns the generated code' => [
            'orderUid' => 1,
            'expectedCode' => 'GAINED-ABC123',
        ];

        yield 'is null when the order generated none' => [
            'orderUid' => 2,
            'expectedCode' => null,
        ];
    }

    #[Test]
    #[DataProvider('fetchCreditPointsLedgerProvider')]
    public function fetchCreditPointsLedger(int $orderUid, ?int $expectedFrontendUser, ?int $expectedPoints, ?string $expectedType): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $ledger = $subject->fetchCreditPointsLedger($orderUid);

        if ($expectedFrontendUser === null) {
            $this->assertSame([], $ledger);
            return;
        }
        $this->assertCount(1, $ledger);
        $this->assertSame($expectedFrontendUser, $ledger[0]['frontendUser']);
        $this->assertSame($expectedPoints, $ledger[0]['points']);
        $this->assertSame($expectedType, $ledger[0]['type']);
    }

    public static function fetchCreditPointsLedgerProvider(): \Generator
    {
        yield 'returns rows for that order' => [
            'orderUid' => 1,
            'expectedFrontendUser' => 5,
            'expectedPoints' => 20,
            'expectedType' => 'earn',
        ];

        yield 'is empty for an order with none' => [
            'orderUid' => 2,
            'expectedFrontendUser' => null,
            'expectedPoints' => null,
            'expectedType' => null,
        ];
    }

    #[Test]
    public function findForEditingAndPersistWritesTheTransitionToTheDatabase(): void
    {
        $subject = $this->get(OrderManagementRepository::class);
        $order = $subject->findForEditing(1);
        $this->assertInstanceOf(Order::class, $order);

        $this->get(OrderStatusManager::class)->transitionPayment($order, PaymentStatus::PAID);
        $subject->persist($order);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/order_management_payment_transition.csv');
    }

    /**
     * Regression: Money-backed fields need explicit @var int docblock for Extbase property reflection.
     */
    #[Test]
    public function findForEditingHydratesAnOrderWithNonZeroMoneyBackedFieldsWithoutCrashing(): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $order = $subject->findForEditing(1);

        $this->assertInstanceOf(Order::class, $order);
        $this->assertSame(1999, $order->getTotalGross()->getCents());
        $this->assertSame(500, $order->getDiscountTotal()->getCents());
        $this->assertSame(500, $order->getShippingTotal()->getCents());
    }
}
