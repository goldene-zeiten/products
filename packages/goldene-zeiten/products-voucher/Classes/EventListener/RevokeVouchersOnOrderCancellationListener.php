<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\EventListener;

use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Voucher\Service\VoucherRevocationService;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Gives back the vouchers of an order cancelled before it was shipped - a failed or abandoned payment, a
 * withdrawal, the backend - and withdraws the reward it earned. A shipped order was delivered, so its
 * vouchers stay used.
 */
#[AsEventListener]
final class RevokeVouchersOnOrderCancellationListener
{
    public function __construct(
        private readonly VoucherRevocationService $voucherRevocationService
    ) {}

    public function __invoke(OrderStatusChangedEvent $event): void
    {
        if ($event->getNewStatus() !== OrderStatus::CANCELLED || $event->getOrderUid() === 0) {
            return;
        }
        if (in_array($event->getPreviousStatus(), [OrderStatus::SHIPPED, OrderStatus::COMPLETED], true)) {
            return;
        }

        $this->voucherRevocationService->revokeForOrder($event->getOrderUid());
    }
}
