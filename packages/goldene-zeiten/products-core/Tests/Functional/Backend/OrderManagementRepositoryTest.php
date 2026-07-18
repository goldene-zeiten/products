<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\OrderListFilter;
use GoldeneZeiten\Products\Core\Backend\OrderManagementRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class OrderManagementRepositoryTest extends AbstractFunctionalTestCase
{
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
}
