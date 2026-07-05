<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Payment;

use GoldeneZeiten\Products\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
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
        private array $rawData = []
    ) {}

    /**
     * @param array<string, mixed> $rawData
     */
    public static function completed(PaymentStatus $paymentStatus, string $externalId = '', array $rawData = []): self
    {
        return new self(PaymentResultState::COMPLETED, $paymentStatus, $externalId, rawData: $rawData);
    }

    /**
     * @param array<string, mixed> $rawData
     */
    public static function pending(string $externalId = '', array $rawData = []): self
    {
        return new self(PaymentResultState::PENDING, PaymentStatus::PENDING, $externalId, rawData: $rawData);
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
     * @return array<string, mixed>
     */
    public function getRawData(): array
    {
        return $this->rawData;
    }
}
