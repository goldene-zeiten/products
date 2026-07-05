<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Event\PaymentStatusChangedEvent;
use GoldeneZeiten\Products\Service\Order\Exception\InvalidOrderStatusTransitionException;
use GoldeneZeiten\Products\Service\Order\Exception\InvalidPaymentStatusTransitionException;
use Psr\EventDispatcher\EventDispatcherInterface;

final class OrderStatusManager
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

    public function transition(Order $order, OrderStatus $target): void
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
        $this->appendStatusLog($order, $current, $target);
        $this->eventDispatcher->dispatch(new OrderStatusChangedEvent($order, $current, $target));
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
        $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent($order, $current, $target));
    }

    private function appendStatusLog(Order $order, OrderStatus $from, OrderStatus $to): void
    {
        $log = $order->getStatusLog();
        $log[] = [
            'from' => $from->value,
            'to' => $to->value,
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        $order->setStatusLog($log);
    }
}
