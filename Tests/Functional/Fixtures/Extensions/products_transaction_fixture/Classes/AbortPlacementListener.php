<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\TransactionFixture;

use GoldeneZeiten\Products\Core\Event\VoucherRedeemedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Fails an order placement at the latest possible moment inside its transaction: VoucherRedeemedEvent is
 * dispatched once the order row, the terms snapshot and the voucher redemption have all been written, so
 * a test that arms this listener proves those writes are rolled back rather than left behind.
 *
 * Disarmed by default, so merely loading this extension changes nothing.
 */
#[AsEventListener]
final class AbortPlacementListener
{
    public static bool $armed = false;

    public function __invoke(VoucherRedeemedEvent $event): void
    {
        if (!self::$armed) {
            return;
        }

        throw new PlacementAbortedException(
            sprintf('Fixture aborted the placement of order "%s".', $event->getOrder()->getOrderNumber()),
            1784073602
        );
    }
}
