<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Fixtures;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;

/**
 * Mutates the order like a real payment method would, to verify such mutations get flushed.
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

    public function getPriority(): int
    {
        return 0;
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
