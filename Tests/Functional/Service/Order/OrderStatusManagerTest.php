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

final class OrderStatusManagerTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private OrderStatusManager $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(OrderStatusManager::class);
    }

    /**
     * @test
     */
    public function transitionChangesStatusAndAppendsLog(): void
    {
        $order = new Order();

        $this->subject->transition($order, OrderStatus::PENDING);

        self::assertSame(OrderStatus::PENDING, $order->getStatus());
        self::assertCount(1, $order->getStatusLog());
        self::assertSame('new', $order->getStatusLog()[0]['from']);
        self::assertSame('pending', $order->getStatusLog()[0]['to']);
    }

    /**
     * @test
     */
    public function transitionToSameStatusIsNoop(): void
    {
        $order = new Order();

        $this->subject->transition($order, OrderStatus::NEW);

        self::assertSame(OrderStatus::NEW, $order->getStatus());
        self::assertCount(0, $order->getStatusLog());
    }

    /**
     * @test
     */
    public function transitionThrowsExceptionForInvalidTransition(): void
    {
        $order = new Order();
        $order->setStatus(OrderStatus::CANCELLED);

        $this->expectException(InvalidOrderStatusTransitionException::class);
        $this->expectExceptionCode(1751751030);

        $this->subject->transition($order, OrderStatus::CONFIRMED);
    }

    /**
     * @test
     */
    public function transitionPaymentChangesPaymentStatus(): void
    {
        $order = new Order();

        $this->subject->transitionPayment($order, PaymentStatus::PAID);

        self::assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
    }

    /**
     * @test
     */
    public function transitionPaymentThrowsExceptionForInvalidTransition(): void
    {
        $order = new Order();
        $order->setPaymentStatus(PaymentStatus::REFUNDED);

        $this->expectException(InvalidPaymentStatusTransitionException::class);
        $this->expectExceptionCode(1751751031);

        $this->subject->transitionPayment($order, PaymentStatus::PAID);
    }
}
