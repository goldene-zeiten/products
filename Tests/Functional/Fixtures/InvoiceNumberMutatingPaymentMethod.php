<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Fixtures;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;

/**
 * Test double for {@see PaymentMethodInterface} that mutates the order it's handed, mirroring
 * the shipped InvoicePaymentMethod::initiate() setting an invoice number - used to prove
 * PaymentInitiationService flushes such mutations regardless of which payment method caused them.
 */
final class InvoiceNumberMutatingPaymentMethod implements PaymentMethodInterface
{
    public const INVOICE_NUMBER = 'MUTATED-BY-PAYMENT-METHOD';

    public function getIdentifier(): string
    {
        return 'invoice-number-mutating-fixture';
    }

    public function getLabel(): string
    {
        return 'Invoice-Number-Mutating Fixture Payment Method';
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return true;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        $order->setInvoiceNumber(self::INVOICE_NUMBER);
        return PaymentResult::completed(PaymentStatus::PENDING, 'FIXTURE-MUTATING');
    }
}
