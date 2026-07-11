<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Service\Order\Exception\InvalidOrderStatusTransitionException;
use GoldeneZeiten\Products\Service\Order\Exception\InvalidPaymentStatusTransitionException;
use GoldeneZeiten\Products\Service\Order\OrderStatusManager;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class OrderStatusManagerTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function transitionChangesStatusAndAppendsLog(): void
    {
        $order = new Order();

        $this->get(OrderStatusManager::class)->transition($order, OrderStatus::PENDING);

        $this->assertSame(OrderStatus::PENDING, $order->getStatus());
        $this->assertCount(1, $order->getStatusLog());
        $this->assertSame('new', $order->getStatusLog()[0]['from']);
        $this->assertSame('pending', $order->getStatusLog()[0]['to']);
    }

    #[Test]
    public function transitionWithANoteAppendsItToTheLogEntry(): void
    {
        $order = new Order();

        $this->get(OrderStatusManager::class)->transition($order, OrderStatus::CANCELLED, 'Customer withdrew from the order.');

        $this->assertSame('Customer withdrew from the order.', $order->getStatusLog()[0]['note']);
    }

    #[Test]
    public function transitionWithoutANoteOmitsItFromTheLogEntry(): void
    {
        $order = new Order();

        $this->get(OrderStatusManager::class)->transition($order, OrderStatus::PENDING);

        $this->assertArrayNotHasKey('note', $order->getStatusLog()[0]);
    }

    #[Test]
    public function transitionToSameStatusIsNoop(): void
    {
        $order = new Order();

        $this->get(OrderStatusManager::class)->transition($order, OrderStatus::NEW);

        $this->assertSame(OrderStatus::NEW, $order->getStatus());
        $this->assertCount(0, $order->getStatusLog());
    }

    #[Test]
    public function transitionThrowsExceptionForInvalidTransition(): void
    {
        $order = new Order();
        $order->setStatus(OrderStatus::CANCELLED);

        $this->expectException(InvalidOrderStatusTransitionException::class);
        $this->expectExceptionCode(1751751030);

        $this->get(OrderStatusManager::class)->transition($order, OrderStatus::CONFIRMED);
    }

    #[Test]
    public function transitionPaymentChangesPaymentStatus(): void
    {
        $order = new Order();

        $this->get(OrderStatusManager::class)->transitionPayment($order, PaymentStatus::PAID);

        $this->assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
    }

    #[Test]
    public function transitionPaymentThrowsExceptionForInvalidTransition(): void
    {
        $order = new Order();
        $order->setPaymentStatus(PaymentStatus::REFUNDED);

        $this->expectException(InvalidPaymentStatusTransitionException::class);
        $this->expectExceptionCode(1751751031);

        $this->get(OrderStatusManager::class)->transitionPayment($order, PaymentStatus::PAID);
    }
}
