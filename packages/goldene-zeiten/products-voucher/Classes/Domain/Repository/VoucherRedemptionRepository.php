<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Domain\Repository;

use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use GoldeneZeiten\Products\Voucher\Domain\Model\VoucherRedemption;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<VoucherRedemption>
 */
final class VoucherRedemptionRepository extends Repository
{
    /**
     * The redemptions that still stand - one reverted by a cancelled order gave its use back.
     */
    public function countFor(Voucher $voucher): int
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->logicalAnd(
            $query->equals('voucherUid', $voucher->getUid()),
            $query->equals('revertedAt', 0)
        ));
        return $query->execute()->count();
    }
}
