<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Domain\Dto;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The outcome of capturing a PayPal order: the overall status, the id of the capture the money moved under
 * and - so the caller can prove the capture belongs to the order it is finalizing - the `custom_id` echoed
 * from the created order and the amount actually settled.
 */
#[Exclude]
final readonly class PaypalCapture
{
    public function __construct(
        public string $status,
        public string $captureId,
        public string $customId = '',
        public ?Money $amount = null,
        public string $currency = '',
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }

    /**
     * Held by PayPal (e.g. for review); the money follows, announced by the capture webhook.
     */
    public function isPending(): bool
    {
        return $this->status === 'PENDING';
    }
}
