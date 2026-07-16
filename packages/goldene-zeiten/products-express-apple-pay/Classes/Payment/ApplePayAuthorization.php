<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Payment;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The processor's answer to authorizing an Apple Pay token: whether the money was approved, and the
 * transaction reference that becomes the order's external payment id. Anything other than approved means no
 * order must be created.
 */
#[Exclude]
final readonly class ApplePayAuthorization
{
    public function __construct(
        private string $status,
        private string $transactionId
    ) {}

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTransactionId(): string
    {
        return $this->transactionId;
    }
}
