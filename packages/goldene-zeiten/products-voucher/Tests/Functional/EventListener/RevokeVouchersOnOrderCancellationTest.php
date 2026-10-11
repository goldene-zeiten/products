<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Tests\Functional\EventListener;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Voucher\Service\VoucherService;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * A cancelled order never took the customer's money for good, so the vouchers it used are given back and
 * the reward voucher it earned is withdrawn - unless the reward was spent already.
 */
final class RevokeVouchersOnOrderCancellationTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-voucher',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/RevokeVouchersOnOrderCancellationTest/cancellable_orders.csv');
    }

    #[Test]
    public function cancellingAnOrderGivesItsRedeemedVoucherBack(): void
    {
        $this->get(OrderStatusManager::class)->transition($this->order(1), OrderStatus::CANCELLED);

        $this->assertSame(0, $this->column('tx_products_domain_model_voucher', 1, 'redemption_count'));
        $this->assertGreaterThan(0, $this->column('tx_products_domain_model_voucherredemption', 1, 'reverted_at'));
        // The single-use voucher may be used again.
        $this->get(VoucherService::class)->resolve('ONCE', Money::fromCents(10000), 5);
    }

    #[Test]
    public function cancellingAnOrderWithdrawsTheUnusedRewardItEarned(): void
    {
        $this->get(OrderStatusManager::class)->transition($this->order(1), OrderStatus::CANCELLED);

        $this->assertSame(1, $this->column('tx_products_domain_model_voucher', 2, 'hidden'));
    }

    #[Test]
    public function aRewardAlreadySpentStaysValid(): void
    {
        $this->get(OrderStatusManager::class)->transition($this->order(2), OrderStatus::CANCELLED);

        $this->assertSame(0, $this->column('tx_products_domain_model_voucher', 3, 'hidden'));
    }

    #[Test]
    public function revokingTwiceGivesTheVoucherBackOnce(): void
    {
        $eventDispatcher = $this->get(EventDispatcherInterface::class);

        $eventDispatcher->dispatch(new OrderStatusChangedEvent(1, OrderStatus::PENDING, OrderStatus::CANCELLED));
        $eventDispatcher->dispatch(new OrderStatusChangedEvent(1, OrderStatus::PENDING, OrderStatus::CANCELLED));

        $this->assertSame(0, $this->column('tx_products_domain_model_voucher', 1, 'redemption_count'));
    }

    #[Test]
    public function aShippedOrderKeepsItsVouchers(): void
    {
        $this->get(EventDispatcherInterface::class)->dispatch(new OrderStatusChangedEvent(1, OrderStatus::SHIPPED, OrderStatus::CANCELLED));

        $this->assertSame(1, $this->column('tx_products_domain_model_voucher', 1, 'redemption_count'));
        $this->assertSame(0, $this->column('tx_products_domain_model_voucher', 2, 'hidden'));
    }

    private function order(int $uid): Order
    {
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage($uid);
        $this->assertInstanceOf(Order::class, $order);
        return $order;
    }

    private function column(string $table, int $uid, string $column): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->select($column)
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
