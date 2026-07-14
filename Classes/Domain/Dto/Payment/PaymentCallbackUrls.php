<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Payment;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The three addresses a payment gateway needs in order to hand the customer - and its own confirmation -
 * back to this shop.
 */
#[Exclude]
final readonly class PaymentCallbackUrls
{
    public function __construct(
        private string $returnUrl = '',
        private string $cancelUrl = '',
        private string $webhookUrl = ''
    ) {}

    public function getReturnUrl(): string
    {
        return $this->returnUrl;
    }

    public function getCancelUrl(): string
    {
        return $this->cancelUrl;
    }

    public function getWebhookUrl(): string
    {
        return $this->webhookUrl;
    }
}
