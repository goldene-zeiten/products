<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentInstructionsProviderInterface;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;

/**
 * Fixture payment method telling the customer how to pay, proving the order mails show those instructions.
 * Never offered in the checkout, so it does not change the methods other tests see.
 */
final class InstructionsPaymentMethod implements PaymentMethodInterface, PaymentInstructionsProviderInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-instructions';
    }

    public function getLabel(): string
    {
        return 'Instructions Fixture Payment';
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return false;
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
        return PaymentResult::completed(PaymentStatus::PENDING, 'FIXTURE-INSTRUCTIONS');
    }

    public function getPaymentInstructions(Order $order): string
    {
        return sprintf('Transfer the total to IBAN DE00 1234 5678, reference %s.', $order->getOrderNumber());
    }
}
