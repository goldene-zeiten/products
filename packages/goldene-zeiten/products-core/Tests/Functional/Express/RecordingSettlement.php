<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\ExpressSettlementInterface;

/**
 * An express settlement that records what it was asked to charge and refund. It reports the amount it was
 * asked for as settled, or - to model a gateway that moved a different amount - the amount it was given.
 */
final class RecordingSettlement implements ExpressSettlementInterface
{
    /** @var Money[] */
    public array $charged = [];

    /** @var PaymentResult[] */
    public array $refunded = [];

    public function __construct(
        private readonly ?Money $reportedAmount = null,
        private readonly ?\Throwable $failure = null,
        private readonly bool $pending = false
    ) {}

    public function settle(Order $order): PaymentResult
    {
        $this->charged[] = $order->getTotalGross();
        if ($this->failure !== null) {
            throw $this->failure;
        }

        if ($this->pending) {
            return PaymentResult::pending('settled-1', settledAmount: $this->reportedAmount ?? $order->getTotalGross());
        }

        return PaymentResult::completed(PaymentStatus::PAID, 'settled-1', settledAmount: $this->reportedAmount ?? $order->getTotalGross());
    }

    public function refund(PaymentResult $settled): void
    {
        $this->refunded[] = $settled;
    }
}
