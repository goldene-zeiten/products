<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Order\OrderCreationService;

/**
 * Notifies integrators when a voucher is redeemed as part of an order — track loyalty
 * redemption, update the customer's reward balance, or sync the transaction to backend systems.
 * Fired during order creation after all applicable vouchers are locked and recorded.
 *
 * @see OrderCreationService::redeemVouchers()
 */
final class VoucherRedeemedEvent
{
    public function __construct(
        private readonly Voucher $voucher,
        private readonly Order $order,
        private readonly Money $discountAmount
    ) {}

    public function getVoucher(): Voucher
    {
        return $this->voucher;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getDiscountAmount(): Money
    {
        return $this->discountAmount;
    }
}
