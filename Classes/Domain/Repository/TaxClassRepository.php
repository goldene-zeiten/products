<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\TaxClass;

/**
 * @extends AbstractReadOnlyRepository<TaxClass>
 */
final class TaxClassRepository extends AbstractReadOnlyRepository
{
    /**
     * Tax classes are shared, storage-page-independent lookup records, same reasoning as
     * ShippingMethodRepository/TaxRateRepository - an explicit method is needed rather than
     * relying on Extbase's magic findOneByCode(), which would respect the storage page by default.
     */
    public function findOneByCode(string $code): ?TaxClass
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('code', $code));
        $taxClass = $query->setLimit(1)->execute()->getFirst();
        return $taxClass instanceof TaxClass ? $taxClass : null;
    }
}
