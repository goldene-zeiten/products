<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Voucher\Discount\VoucherDiscountProvider;
use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;

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
