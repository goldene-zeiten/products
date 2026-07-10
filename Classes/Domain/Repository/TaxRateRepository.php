<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Model\TaxRate;

/**
 * @extends AbstractReadOnlyRepository<TaxRate>
 */
final class TaxRateRepository extends AbstractReadOnlyRepository
{
    public function findByTaxClassAndCountry(TaxClass $taxClass, string $countryCode, \DateTimeInterface $now): ?TaxRate
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $constraints = [
            $query->equals('taxClass', $taxClass),
            $query->equals('country', $countryCode),
            $query->logicalOr(
                $query->equals('validFrom', null),
                $query->lessThanOrEqual('validFrom', $now)
            ),
            $query->logicalOr(
                $query->equals('validUntil', null),
                $query->equals('validUntil', 0),
                $query->greaterThanOrEqual('validUntil', $now)
            ),
        ];

        $taxRate = $query->matching($query->logicalAnd(...$constraints))
            ->setLimit(1)
            ->execute()
            ->getFirst();

        return $taxRate instanceof TaxRate ? $taxRate : null;
    }
}
