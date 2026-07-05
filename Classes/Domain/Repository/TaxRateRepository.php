<?php
declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\TaxClass;
use GoldeneZeiten\Products\Domain\Model\TaxRate;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<TaxRate>
 */
final class TaxRateRepository extends Repository
{
    public function findByTaxClassAndCountry(TaxClass $taxClass, string $countryCode, \DateTimeInterface $now): ?TaxRate
    {
        $query = $this->createQuery();
        $constraints = [
            $query->equals('taxClass', $taxClass),
            $query->equals('country', $countryCode),
            $query->logicalOr(
                $query->equals('validFrom', null),
                $query->lessThanOrEqual('validFrom', $now)
            ),
            $query->logicalOr(
                $query->equals('validUntil', null),
                $query->greaterThanOrEqual('validUntil', $now)
            ),
        ];

        return $query->matching($query->logicalAnd(...$constraints))
            ->setLimit(1)
            ->execute()
            ->getFirst();
    }
}
