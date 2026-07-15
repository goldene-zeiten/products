<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Domain\Repository;

use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Write path for auto-issued vouchers; {@see VoucherRepository} is read-only.
 *
 * @extends Repository<Voucher>
 */
final class GainedVoucherRepository extends Repository
{
    public function __construct()
    {
        parent::__construct();
        $this->objectType = Voucher::class;
    }
}
