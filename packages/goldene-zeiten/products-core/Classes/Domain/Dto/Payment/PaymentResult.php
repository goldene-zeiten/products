<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Payment;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class PaymentResult
{
    /**
     * @param array<string, mixed> $rawData
     */
    private function __construct(
        private PaymentResultState $state,
        private PaymentStatus $paymentStatus,
        private string $externalId = '',
        private string $redirectUrl = '',
        private string $failureReason = '',
        private array $rawData = [],
        private ?Money $settledAmount = null
    ) {}

    /**
     * @param array<string, mixed> $rawData
     */
    public static function completed(PaymentStatus $paymentStatus, string $externalId = '', array $rawData = [], ?Money $settledAmount = null): self
    {
        return new self(PaymentResultState::COMPLETED, $paymentStatus, $externalId, rawData: $rawData, settledAmount: $settledAmount);
    }

    /**
     * @param array<string, mixed> $rawData
     */
    public static function pending(string $externalId = '', array $rawData = [], ?Money $settledAmount = null): self
    {
        return new self(PaymentResultState::PENDING, PaymentStatus::PENDING, $externalId, rawData: $rawData, settledAmount: $settledAmount);
    }

    /**
     * Nothing to act on yet: the customer is still paying at the gateway, or a notification concerns
     * something else. The order and its payment stay as they are.
     */
    public static function unchanged(string $externalId = ''): self
    {
        return new self(PaymentResultState::UNCHANGED, PaymentStatus::OPEN, $externalId);
    }

    public static function redirectRequired(string $redirectUrl, string $externalId = ''): self
    {
        return new self(PaymentResultState::REDIRECT_REQUIRED, PaymentStatus::OPEN, $externalId, redirectUrl: $redirectUrl);
    }

    public static function failed(string $reason, string $externalId = ''): self
    {
        return new self(PaymentResultState::FAILED, PaymentStatus::FAILED, $externalId, failureReason: $reason);
    }

    public function getState(): PaymentResultState
    {
        return $this->state;
    }

    public function getPaymentStatus(): PaymentStatus
    {
        return $this->paymentStatus;
    }

    public function getExternalId(): string
    {
        return $this->externalId;
    }

    public function getRedirectUrl(): string
    {
        return $this->redirectUrl;
    }

    public function getFailureReason(): string
    {
        return $this->failureReason;
    }

    /**
     * The amount the gateway reports it actually moved, where the payment settles before the order exists
     * (express checkout); null where the order total was never in question.
     */
    public function getSettledAmount(): ?Money
    {
        return $this->settledAmount;
    }

    /**
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }
}
