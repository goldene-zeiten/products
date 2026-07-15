<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class CheckoutChoices
{
    public function __construct(
        private string $shippingOptionKey = '',
        private ?Address $deliveryAddress = null,
        private string $giftMessage = '',
        private bool $termsAccepted = false
    ) {}

    public function getShippingOptionKey(): string
    {
        return $this->shippingOptionKey;
    }

    public function getDeliveryAddress(): ?Address
    {
        return $this->deliveryAddress;
    }

    public function getGiftMessage(): string
    {
        return $this->giftMessage;
    }

    public function isTermsAccepted(): bool
    {
        return $this->termsAccepted;
    }
}
