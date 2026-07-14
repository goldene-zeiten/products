<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Payment;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class PaymentContext
{
    public function __construct(
        private Money $amount,
        private string $currency,
        private string $countryCode,
        private int $frontendUserUid = 0,
        private string $returnUrl = '',
        private string $cancelUrl = '',
        private string $webhookUrl = ''
    ) {}

    public function getAmount(): Money
    {
        return $this->amount;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getCountryCode(): string
    {
        return $this->countryCode;
    }

    public function getFrontendUserUid(): int
    {
        return $this->frontendUserUid;
    }

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
