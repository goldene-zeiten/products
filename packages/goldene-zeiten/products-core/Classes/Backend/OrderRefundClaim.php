<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Event\PaymentStatusChangedEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Reserves an order's refund for one caller. Two requests refunding the same order at once both read it as
 * paid, so the reservation is decided on the order row itself: only the request whose conditional update
 * changes the row may call the gateway. Release the claim when the gateway did not take the refund.
 */
final class OrderRefundClaim
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    /**
     * @return bool whether this call reserved the refund
     */
    public function claim(int $orderUid): bool
    {
        if (!$this->compareAndSet($orderUid, PaymentStatus::PAID, PaymentStatus::REFUND_PENDING)) {
            return false;
        }
        $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent($orderUid, PaymentStatus::PAID, PaymentStatus::REFUND_PENDING));

        return true;
    }

    public function release(int $orderUid): void
    {
        if ($this->compareAndSet($orderUid, PaymentStatus::REFUND_PENDING, PaymentStatus::PAID)) {
            $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent($orderUid, PaymentStatus::REFUND_PENDING, PaymentStatus::PAID));
        }
    }

    private function compareAndSet(int $orderUid, PaymentStatus $expected, PaymentStatus $target): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_order');
        $queryBuilder->getRestrictions()->removeAll();
        $affectedRows = $queryBuilder
            ->update('tx_products_domain_model_order')
            ->set('payment_status', $queryBuilder->createNamedParameter($target->value), false)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('payment_status', $queryBuilder->createNamedParameter($expected->value))
            )
            ->executeStatement();

        return $affectedRows === 1;
    }
}
