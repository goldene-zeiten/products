<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Fixtures;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;

/**
 * Test double for {@see PaymentMethodInterface} returning a caller-chosen {@see PaymentResult}.
 */
final class FixturePaymentMethod implements PaymentMethodInterface
{
    public function __construct(
        private readonly PaymentResult $result
    ) {}

    public static function pending(string $externalId = 'FIXTURE-PENDING'): self
    {
        return new self(PaymentResult::pending($externalId));
    }

    public static function completed(string $externalId = 'FIXTURE-COMPLETED'): self
    {
        return new self(PaymentResult::completed(PaymentStatus::PENDING, $externalId));
    }

    public function getIdentifier(): string
    {
        return 'fixture';
    }

    public function getLabel(): string
    {
        return 'Fixture Payment Method';
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
        return $this->result;
    }
}
