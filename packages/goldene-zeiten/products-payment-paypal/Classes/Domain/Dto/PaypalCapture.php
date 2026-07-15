<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The outcome of capturing a PayPal order: the overall status and, when successful, the id of the capture
 * the money moved under.
 */
#[Exclude]
final readonly class PaypalCapture
{
    public function __construct(
        public string $status,
        public string $captureId,
    ) {}

    public function isCompleted(): bool
    {
        return $this->status === 'COMPLETED';
    }
}
