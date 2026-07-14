<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;

/**
 * Fixture payment method that is unavailable, proving isAvailable() filtering is honoured.
 */
final class UnavailablePaymentMethod implements PaymentMethodInterface
{
    public function getIdentifier(): string
    {
        return 'fixture-unavailable';
    }

    public function getLabel(): string
    {
        return 'Unavailable Fixture Payment';
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
        return PaymentResult::completed(PaymentStatus::PENDING, 'FIXTURE-UNAVAILABLE');
    }
}
