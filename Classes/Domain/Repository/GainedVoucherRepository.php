<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Voucher;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * Write path for auto-issued "gained" vouchers. Separate from the read-only VoucherRepository
 * (BE-managed codes) because Repository derives its managed type from the class name, and
 * VoucherRepository already owns the "Voucher" name for that read-only contract.
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
