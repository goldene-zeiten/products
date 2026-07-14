<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Discount\VoucherDiscountProvider;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\ValueObject\Money;

/**
 * Notifies integrators when a voucher is redeemed as part of an order — track loyalty
 * redemption, update the customer's reward balance, or sync the transaction to backend systems.
 * Fired during order placement, once the voucher has been locked and its redemption recorded.
 *
 * @see VoucherDiscountProvider::apply()
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
