<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\Voucher;
use GoldeneZeiten\Products\Core\Domain\Model\VoucherRedemption;
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
