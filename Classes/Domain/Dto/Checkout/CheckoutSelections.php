<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Domain\Dto\Address;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class CheckoutSelections
{
    /**
     * @param string[] $voucherCodes
     */
    public function __construct(
        private array $voucherCodes,
        private int $spendPoints,
        private int $shippingMethodUid = 0,
        private ?Address $deliveryAddress = null,
        private string $giftMessage = ''
    ) {}

    /**
     * @return string[]
     */
    public function getVoucherCodes(): array
    {
        return $this->voucherCodes;
    }

    public function getSpendPoints(): int
    {
        return $this->spendPoints;
    }

    public function getShippingMethodUid(): int
    {
        return $this->shippingMethodUid;
    }

    public function getDeliveryAddress(): ?Address
    {
        return $this->deliveryAddress;
    }

    public function getGiftMessage(): string
    {
        return $this->giftMessage;
    }
}
