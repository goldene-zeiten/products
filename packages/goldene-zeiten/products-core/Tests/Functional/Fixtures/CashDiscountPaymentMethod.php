<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Fixtures;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;

/**
 * Test double granting 3 % off for paying cash, and remembering the shipping it was offered with.
 */
final class CashDiscountPaymentMethod implements PaymentMethodInterface
{
    public string $seenShippingOptionKey = '';

    public function getIdentifier(): string
    {
        return 'fixture-cash';
    }

    public function getLabel(): string
    {
        return 'Cash (3 % discount)';
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
        $this->seenShippingOptionKey = $context->getShippingOptionKey();
        return -(int)round($context->getAmount()->getCents() * 0.03);
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::PENDING, 'FIXTURE-CASH');
    }
}
