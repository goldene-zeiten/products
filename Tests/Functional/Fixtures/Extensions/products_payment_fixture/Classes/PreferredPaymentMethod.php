<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;

/**
 * Fixture payment method with high priority (100), proving priority sorting descending.
 */
final class PreferredPaymentMethod implements PaymentMethodInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-preferred';
    }

    public function getLabel(): string
    {
        return 'Preferred Fixture Payment';
    }

    public function isAvailable(PaymentContext $context): bool
    {
        return true;
    }

    public function getPriority(): int
    {
        return 100;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::PENDING, 'FIXTURE-PREFERRED');
    }
}
