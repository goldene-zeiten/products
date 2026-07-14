<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\InvoicePaymentMethod;
use GoldeneZeiten\Products\Core\Payment\RefundablePaymentMethodInterface;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class InvoicePaymentMethodTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function implementsTheRefundableInterface(): void
    {
        $subject = $this->get(InvoicePaymentMethod::class);

        $this->assertInstanceOf(RefundablePaymentMethodInterface::class, $subject);
    }

    #[Test]
    public function refundCompletesWithTheRefundedPaymentStatus(): void
    {
        $subject = $this->get(InvoicePaymentMethod::class);
        $order = new Order();
        $order->setInvoiceNumber('INV-1');

        $result = $subject->refund($order, Money::fromCents(1999));

        $this->assertSame(PaymentResultState::COMPLETED, $result->getState());
        $this->assertSame(PaymentStatus::REFUNDED, $result->getPaymentStatus());
        $this->assertSame('INV-1', $result->getExternalId());
    }

    #[Test]
    public function cancelCompletesWithTheFailedPaymentStatus(): void
    {
        $subject = $this->get(InvoicePaymentMethod::class);
        $order = new Order();
        $order->setInvoiceNumber('INV-2');

        $result = $subject->cancel($order);

        $this->assertSame(PaymentResultState::COMPLETED, $result->getState());
        $this->assertSame(PaymentStatus::FAILED, $result->getPaymentStatus());
    }
}
