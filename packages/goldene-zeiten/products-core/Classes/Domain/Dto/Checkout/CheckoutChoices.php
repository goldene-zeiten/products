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
        private bool $termsAccepted = false,
        private bool $withdrawalWaiverAccepted = false,
        private string $customerNote = '',
        private ?\DateTimeImmutable $desiredDeliveryDate = null,
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

    public function isWithdrawalWaiverAccepted(): bool
    {
        return $this->withdrawalWaiverAccepted;
    }

    public function getCustomerNote(): string
    {
        return $this->customerNote;
    }

    public function getDesiredDeliveryDate(): ?\DateTimeImmutable
    {
        return $this->desiredDeliveryDate;
    }
}
