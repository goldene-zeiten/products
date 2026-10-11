<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Core\Event\PaymentStatusChangedEvent;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InvalidOrderStatusTransitionException;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InvalidPaymentStatusTransitionException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class OrderStatusManager
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * Moves a persisted order to the target status unless another request got there first. Payment
     * callbacks race each other (the customer's return and the gateway's webhook), each holding the order
     * as it was loaded, so the transition is decided on the order row itself: only the request whose
     * conditional update changes the row wins, and only the winner logs the change and dispatches its
     * event. A loser's order object is brought up to date with the row instead.
     *
     * @return bool whether this call made the transition
     */
    public function claimTransition(Order $order, OrderStatus $target, ?string $note = null): bool
    {
        $orderUid = (int)$order->getUid();
        $current = OrderStatus::from($this->fetchColumn($orderUid, 'status'));
        if ($current === $target || !$current->canTransitionTo($target)
            || !$this->compareAndSet($orderUid, 'status', $current->value, $target->value)
        ) {
            $this->syncStatus($order, OrderStatus::from($this->fetchColumn($orderUid, 'status')));
            return false;
        }

        $order->setStatus($target);
        $this->appendStatusLog($order, $current, $target, $note);
        $this->eventDispatcher->dispatch(new OrderStatusChangedEvent($orderUid, $current, $target));
        return true;
    }

    /**
     * The payment-status counterpart of {@see claimTransition()}. A transition the current payment status
     * does not allow - a late "pending" for a payment that is already paid - is ignored rather than
     * rejected, since gateways deliver their notifications in no guaranteed order.
     *
     * @return bool whether this call made the transition
     */
    public function claimPaymentTransition(Order $order, PaymentStatus $target): bool
    {
        $orderUid = (int)$order->getUid();
        $current = PaymentStatus::from($this->fetchColumn($orderUid, 'payment_status'));
        if ($current === $target || !$current->canTransitionTo($target)
            || !$this->compareAndSet($orderUid, 'payment_status', $current->value, $target->value)
        ) {
            $this->syncPaymentStatus($order, PaymentStatus::from($this->fetchColumn($orderUid, 'payment_status')));
            return false;
        }

        $order->setPaymentStatus($target);
        $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent($orderUid, $current, $target));
        return true;
    }

    public function transition(Order $order, OrderStatus $target, ?string $note = null): void
    {
        $current = $order->getStatus();
        if ($current === $target) {
            return;
        }
        if (!$current->canTransitionTo($target)) {
            throw new InvalidOrderStatusTransitionException(
                sprintf('Order status cannot transition from "%s" to "%s".', $current->value, $target->value),
                1751751030
            );
        }

        $order->setStatus($target);
        $this->appendStatusLog($order, $current, $target, $note);
        $this->recordDeliveryOnCompletion($order, $current, $target);
        $this->eventDispatcher->dispatch(new OrderStatusChangedEvent((int)$order->getUid(), $current, $target));
    }

    /**
     * Completing an order means its goods arrived, which is the moment the withdrawal period starts.
     * Goods that come back after a withdrawal were delivered long before.
     */
    private function recordDeliveryOnCompletion(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        if ($to === OrderStatus::COMPLETED && $from !== OrderStatus::WITHDRAWN && $order->getDeliveredAt() === null) {
            $order->setDeliveredAt(new \DateTime());
        }
    }

    public function transitionPayment(Order $order, PaymentStatus $target): void
    {
        $current = $order->getPaymentStatus();
        if ($current === $target) {
            return;
        }
        if (!$current->canTransitionTo($target)) {
            throw new InvalidPaymentStatusTransitionException(
                sprintf('Payment status cannot transition from "%s" to "%s".', $current->value, $target->value),
                1751751031
            );
        }

        $order->setPaymentStatus($target);
        $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent((int)$order->getUid(), $current, $target));
    }

    private function fetchColumn(int $orderUid, string $column): string
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_order');
        $queryBuilder->getRestrictions()->removeAll();
        return (string)$queryBuilder
            ->select($column)
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function compareAndSet(int $orderUid, string $column, string $expected, string $target): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_order');
        $queryBuilder->getRestrictions()->removeAll();
        $affectedRows = $queryBuilder
            ->update('tx_products_domain_model_order')
            ->set($column, $queryBuilder->createNamedParameter($target), false)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq($column, $queryBuilder->createNamedParameter($expected))
            )
            ->executeStatement();

        return $affectedRows === 1;
    }

    private function syncStatus(Order $order, OrderStatus $persisted): void
    {
        if ($order->getStatus() !== $persisted) {
            $order->setStatus($persisted);
        }
    }

    private function syncPaymentStatus(Order $order, PaymentStatus $persisted): void
    {
        if ($order->getPaymentStatus() !== $persisted) {
            $order->setPaymentStatus($persisted);
        }
    }

    private function appendStatusLog(Order $order, OrderStatus $from, OrderStatus $to, ?string $note): void
    {
        $log = $order->getStatusLog();
        $entry = [
            'from' => $from->value,
            'to' => $to->value,
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        if ($note !== null && $note !== '') {
            $entry['note'] = $note;
        }
        $log[] = $entry;
        $order->setStatusLog($log);
    }
}
