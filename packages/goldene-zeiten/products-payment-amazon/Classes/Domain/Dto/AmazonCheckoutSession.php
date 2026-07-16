<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Domain\Dto;

/**
 * The parts of an Amazon Pay Checkout Session the payment method acts on, decoded from the JSON of any
 * Create/Get/Update/Complete response (they share the same object shape).
 */
final readonly class AmazonCheckoutSession
{
    public function __construct(
        public string $checkoutSessionId,
        public string $state,
        public string $reasonCode,
        public string $amazonPayRedirectUrl,
        public string $chargeId,
        public string $chargePermissionId,
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $statusDetails = is_array($data['statusDetails'] ?? null) ? $data['statusDetails'] : [];
        $webCheckoutDetails = is_array($data['webCheckoutDetails'] ?? null) ? $data['webCheckoutDetails'] : [];

        return new self(
            (string)($data['checkoutSessionId'] ?? ''),
            (string)($statusDetails['state'] ?? ''),
            (string)($statusDetails['reasonCode'] ?? ''),
            (string)($webCheckoutDetails['amazonPayRedirectUrl'] ?? ''),
            (string)($data['chargeId'] ?? ''),
            (string)($data['chargePermissionId'] ?? ''),
        );
    }

    public function isOpen(): bool
    {
        return $this->state === 'Open';
    }

    public function isCompleted(): bool
    {
        return $this->state === 'Completed';
    }

    public function isCanceled(): bool
    {
        return $this->state === 'Canceled';
    }
}
