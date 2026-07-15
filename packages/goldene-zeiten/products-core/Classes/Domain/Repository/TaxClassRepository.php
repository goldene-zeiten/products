<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\TaxClass;

/**
 * @extends AbstractReadOnlyRepository<TaxClass>
 */
final class TaxClassRepository extends AbstractReadOnlyRepository
{
    public function findOneByCode(string $code): ?TaxClass
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('code', $code));
        $taxClass = $query->setLimit(1)->execute()->getFirst();
        return $taxClass instanceof TaxClass ? $taxClass : null;
    }
}
