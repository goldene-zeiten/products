<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
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
        private string $webhookUrl = '',
        private string $shippingOptionKey = ''
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

    /**
     * The shipping option chosen for the order, e.g. "tablerate:12"; empty when nothing ships or nothing
     * was chosen yet. Lets a method depend on the delivery, e.g. cash only when collected in store.
     */
    public function getShippingOptionKey(): string
    {
        return $this->shippingOptionKey;
    }

    public function getShippingProviderIdentifier(): string
    {
        return $this->shippingOptionKey !== '' ? ShippingOption::splitKey($this->shippingOptionKey)[0] : '';
    }

    /**
     * Whether the chosen shipping is one of the given entries, each a provider ("pickup") or a single
     * option ("ups:express"). False while no shipping is chosen.
     *
     * @param string[] $providersOrOptions
     */
    public function isShippedWithAnyOf(array $providersOrOptions): bool
    {
        if ($this->shippingOptionKey === '') {
            return false;
        }
        return in_array($this->shippingOptionKey, $providersOrOptions, true)
            || in_array($this->getShippingProviderIdentifier(), $providersOrOptions, true);
    }
}
