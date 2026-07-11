<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Fixtures;

use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;

/**
 * Test double for {@see PaymentMethodInterface} that returns a caller-chosen, otherwise
 * unremarkable {@see PaymentResult} without touching the order - used by tests that only care
 * about PaymentInitiationService's own transaction-bookkeeping behaviour.
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

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return $this->result;
    }
}
