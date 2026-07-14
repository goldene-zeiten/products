<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;

/**
 * Fixture payment method with zero fee, proving tagged_iterator wiring.
 */
final class FreePaymentMethod implements PaymentMethodInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-free';
    }

    public function getLabel(): string
    {
        return 'Free Fixture Payment';
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
        return PaymentResult::completed(PaymentStatus::PENDING, 'FIXTURE-FREE');
    }
}
