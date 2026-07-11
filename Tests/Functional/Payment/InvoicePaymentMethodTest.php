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
use PHPUnit\Framework\Attributes\Test;

final class InvoicePaymentMethodTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function implementsTheRefundableInterface(): void
    {
        $this->assertInstanceOf(RefundablePaymentMethodInterface::class, $this->get(InvoicePaymentMethod::class));
    }

    #[Test]
    public function refundCompletesWithTheRefundedPaymentStatus(): void
    {
        $order = new Order();
        $order->setInvoiceNumber('INV-1');

        $result = $this->get(InvoicePaymentMethod::class)->refund($order, Money::fromCents(1999));

        $this->assertSame(PaymentResultState::COMPLETED, $result->getState());
        $this->assertSame(PaymentStatus::REFUNDED, $result->getPaymentStatus());
        $this->assertSame('INV-1', $result->getExternalId());
    }

    #[Test]
    public function cancelCompletesWithTheFailedPaymentStatus(): void
    {
        $order = new Order();
        $order->setInvoiceNumber('INV-2');

        $result = $this->get(InvoicePaymentMethod::class)->cancel($order);

        $this->assertSame(PaymentResultState::COMPLETED, $result->getState());
        $this->assertSame(PaymentStatus::FAILED, $result->getPaymentStatus());
    }
}
