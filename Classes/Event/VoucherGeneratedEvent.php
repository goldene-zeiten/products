<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Event;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Service\Voucher\GainedVoucherService;

/**
 * Notifies integrators when a reward voucher is auto-generated for a customer — log the
 * voucher code, notify the customer about their reward, or sync it to a loyalty system.
 * Fired after an order is placed if it qualifies for automatic voucher generation.
 *
 * @see GainedVoucherService::maybeIssue()
 */
final class VoucherGeneratedEvent
{
    public function __construct(
        private readonly Voucher $voucher,
        private readonly Order $order
    ) {}

    public function getVoucher(): Voucher
    {
        return $this->voucher;
    }

    public function getOrder(): Order
    {
        return $this->order;
    }
}
