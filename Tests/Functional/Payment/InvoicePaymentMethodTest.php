<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Payment;

use GoldeneZeiten\Products\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\InvoicePaymentMethod;
use GoldeneZeiten\Products\Payment\RefundablePaymentMethodInterface;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;

final class InvoicePaymentMethodTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private InvoicePaymentMethod $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(InvoicePaymentMethod::class);
    }

    /**
     * @test
     */
    public function implementsTheRefundableInterface(): void
    {
        self::assertInstanceOf(RefundablePaymentMethodInterface::class, $this->subject);
    }

    /**
     * @test
     */
    public function refundCompletesWithTheRefundedPaymentStatus(): void
    {
        $order = new Order();
        $order->setInvoiceNumber('INV-1');

        $result = $this->subject->refund($order, Money::fromCents(1999));

        self::assertSame(PaymentResultState::COMPLETED, $result->getState());
        self::assertSame(PaymentStatus::REFUNDED, $result->getPaymentStatus());
        self::assertSame('INV-1', $result->getExternalId());
    }

    /**
     * @test
     */
    public function cancelCompletesWithTheFailedPaymentStatus(): void
    {
        $order = new Order();
        $order->setInvoiceNumber('INV-2');

        $result = $this->subject->cancel($order);

        self::assertSame(PaymentResultState::COMPLETED, $result->getState());
        self::assertSame(PaymentStatus::FAILED, $result->getPaymentStatus());
    }
}
