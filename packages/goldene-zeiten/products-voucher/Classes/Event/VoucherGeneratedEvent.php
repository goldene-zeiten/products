<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Event;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use GoldeneZeiten\Products\Voucher\Service\GainedVoucherService;

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
