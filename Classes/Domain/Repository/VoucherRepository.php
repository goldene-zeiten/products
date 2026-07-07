<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Voucher;

/**
 * @extends AbstractReadOnlyRepository<Voucher>
 */
final class VoucherRepository extends AbstractReadOnlyRepository
{
    public function findOneByCode(string $code): ?Voucher
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('code', $code));
        $result = $query->execute()->getFirst();
        return $result instanceof Voucher ? $result : null;
    }
}
