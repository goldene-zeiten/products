<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\OrderManagementRepository;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The backend order view shows the withdrawal facts the trader acts on: when the customer withdrew, when
 * the refund is due (14 days later, Art. 13 Directive 2011/83/EU) and the return milestones.
 */
final class OrderManagementRepositoryWithdrawalTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderReturnServiceTest/orders.csv');
    }

    #[Test]
    public function aWithdrawnOrderShowsTheRefundDeadlineFourteenDaysAfterTheWithdrawal(): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $row = $subject->fetchRow(5, $this->setUpBackendUser(1));

        $this->assertNotNull($row);
        $this->assertSame(date('Y-m-d', 1790000000 + 14 * 86400), $row['refundDueBy']);
        $this->assertSame(date('Y-m-d H:i', 1790000000), $row['withdrawnAt']);
        $this->assertSame(date('Y-m-d H:i', 1790100000), $row['returnProofAt']);
        $this->assertNull($row['returnedAt']);
    }

    #[Test]
    public function anOrderWithoutWithdrawalHasNoRefundDeadline(): void
    {
        $subject = $this->get(OrderManagementRepository::class);

        $row = $subject->fetchRow(1, $this->setUpBackendUser(1));

        $this->assertNotNull($row);
        $this->assertNull($row['refundDueBy']);
        $this->assertNull($row['deliveredAt']);
    }
}
