<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;

/**
 * Fixture payment method with 2.50 EUR surcharge, proving fee calculation is wired into order total.
 */
final class SurchargePaymentMethod implements PaymentMethodInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-surcharge';
    }

    public function getLabel(): string
    {
        return 'Surcharge Fixture Payment';
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
        return 250;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::PENDING, 'FIXTURE-SURCHARGE');
    }
}
