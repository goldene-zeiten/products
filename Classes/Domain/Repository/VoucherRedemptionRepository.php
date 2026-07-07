<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Model\VoucherRedemption;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<VoucherRedemption>
 */
final class VoucherRedemptionRepository extends Repository
{
    public function countFor(Voucher $voucher): int
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('voucherUid', $voucher->getUid()));
        return $query->execute()->count();
    }
}
