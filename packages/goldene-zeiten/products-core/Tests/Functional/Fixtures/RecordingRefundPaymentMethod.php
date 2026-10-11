<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Fixtures;

use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\RefundablePaymentMethodInterface;

/**
 * Refundable test double counting how often the gateway was asked to refund.
 */
final class RecordingRefundPaymentMethod implements PaymentMethodInterface, RefundablePaymentMethodInterface
{
    public int $refundCalls = 0;
    public ?Money $lastAmount = null;

    /**
     * @param (\Closure(): void)|null $duringRefund runs while the gateway call is in flight
     */
    public function __construct(
        private readonly ?PaymentResult $refundResult = null,
        private readonly ?\Closure $duringRefund = null,
        private readonly ?\Throwable $failure = null
    ) {}

    public function getIdentifier(): string
    {
        return 'recording-refund';
    }

    public function getLabel(): string
    {
        return 'Recording Refund Fixture';
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
        return PaymentResult::completed(PaymentStatus::PAID, 'RECORDING');
    }

    public function cancel(OrderData $order): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::FAILED, 'RECORDING');
    }

    public function refund(OrderData $order, Money $amount): PaymentResult
    {
        $this->refundCalls++;
        $this->lastAmount = $amount;
        if ($this->duringRefund !== null) {
            ($this->duringRefund)();
        }
        if ($this->failure !== null) {
            throw $this->failure;
        }

        return $this->refundResult ?? PaymentResult::completed(PaymentStatus::REFUNDED, 'RECORDING');
    }
}
