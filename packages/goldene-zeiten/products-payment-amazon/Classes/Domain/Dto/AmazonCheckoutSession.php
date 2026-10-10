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
        public string $merchantReferenceId = '',
        public string $chargeAmount = '',
        public string $chargeCurrency = '',
    ) {}

    /**
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $statusDetails = is_array($data['statusDetails'] ?? null) ? $data['statusDetails'] : [];
        $webCheckoutDetails = is_array($data['webCheckoutDetails'] ?? null) ? $data['webCheckoutDetails'] : [];
        $merchantMetadata = is_array($data['merchantMetadata'] ?? null) ? $data['merchantMetadata'] : [];
        $paymentDetails = is_array($data['paymentDetails'] ?? null) ? $data['paymentDetails'] : [];
        $chargeAmount = is_array($paymentDetails['chargeAmount'] ?? null) ? $paymentDetails['chargeAmount'] : [];

        return new self(
            (string)($data['checkoutSessionId'] ?? ''),
            (string)($statusDetails['state'] ?? ''),
            (string)($statusDetails['reasonCode'] ?? ''),
            (string)($webCheckoutDetails['amazonPayRedirectUrl'] ?? ''),
            (string)($data['chargeId'] ?? ''),
            (string)($data['chargePermissionId'] ?? ''),
            (string)($merchantMetadata['merchantReferenceId'] ?? ''),
            (string)($chargeAmount['amount'] ?? ''),
            (string)($chargeAmount['currencyCode'] ?? ''),
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
