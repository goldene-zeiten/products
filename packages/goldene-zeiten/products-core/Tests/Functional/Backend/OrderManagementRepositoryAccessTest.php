<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\OrderListFilter;
use GoldeneZeiten\Products\Core\Backend\OrderManagementRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Orders carry customer data, so the order module lists and shows only the orders stored on pages the
 * backend user may read, and only to users allowed to read the order table at all.
 */
final class OrderManagementRepositoryAccessTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderManagementRepositoryAccessTest/orders_on_two_sites.csv');
    }

    /**
     * @param string[] $expectedOrderNumbers
     */
    #[Test]
    #[DataProvider('visibleOrdersProvider')]
    public function theListContainsOnlyOrdersTheUserMayRead(int $backendUserUid, array $expectedOrderNumbers): void
    {
        $backendUser = $this->setUpBackendUser($backendUserUid);
        $subject = $this->get(OrderManagementRepository::class);

        $orders = $subject->fetchFiltered(new OrderListFilter(), $backendUser);

        $orderNumbers = array_column($orders, 'orderNumber');
        sort($orderNumbers);
        $this->assertSame($expectedOrderNumbers, $orderNumbers);
    }

    public static function visibleOrdersProvider(): \Generator
    {
        yield 'admin sees the orders of every site' => [
            'backendUserUid' => 1,
            'expectedOrderNumbers' => ['ORD-A', 'ORD-B'],
        ];
        yield 'reader sees the orders below the own web mount only' => [
            'backendUserUid' => 2,
            'expectedOrderNumbers' => ['ORD-A'],
        ];
        yield 'user without read access to the order table sees nothing' => [
            'backendUserUid' => 3,
            'expectedOrderNumbers' => [],
        ];
    }

    #[Test]
    public function anOrderOutsideTheUsersWebMountIsNotShown(): void
    {
        $backendUser = $this->setUpBackendUser(2);
        $subject = $this->get(OrderManagementRepository::class);

        $this->assertNull($subject->fetchRow(2, $backendUser));
        $this->assertNotNull($subject->fetchRow(1, $backendUser));
    }
}
