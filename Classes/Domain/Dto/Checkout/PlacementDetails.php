<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto\Checkout;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Everything OrderFactory needs beyond the basic request/basket/address/paymentMethod: the
 * resolved amounts that adjust the order's totals (vouchers and points reduce it, shipping adds
 * to it), plus an optional alternate delivery address/gift message it snapshots as-is.
 */
#[Exclude]
final readonly class PlacementDetails
{
    public function __construct(
        private BasketDiscountSummary $voucherSummary,
        private Money $pointsDiscountAmount,
        private ShippingSelection $shippingSelection,
        private Money $handlingFeeCost,
        private ?Address $deliveryAddress = null,
        private string $giftMessage = ''
    ) {}

    public function getTotalDiscount(): Money
    {
        return $this->voucherSummary->getDiscountTotal()->add($this->pointsDiscountAmount);
    }

    /**
     * @return string[]
     */
    public function getVoucherCodes(): array
    {
        return array_map(
            static fn(Voucher $voucher): string => $voucher->getCode(),
            $this->voucherSummary->getAppliedVouchers()
        );
    }

    public function getShippingCost(): Money
    {
        return $this->shippingSelection->getCost();
    }

    public function getShippingMethodUid(): int
    {
        return $this->shippingSelection->getShippingMethodUid();
    }

    public function getHandlingFeeCost(): Money
    {
        return $this->handlingFeeCost;
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
