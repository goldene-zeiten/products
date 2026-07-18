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

final class OrderStatusManager
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher
    ) {}

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
        $this->eventDispatcher->dispatch(new OrderStatusChangedEvent((int)$order->getUid(), $current, $target));
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
