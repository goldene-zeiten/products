<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\ShippingMethod;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * @extends AbstractReadOnlyRepository<ShippingMethod>
 */
final class ShippingMethodRepository extends AbstractReadOnlyRepository
{
    protected $defaultOrderings = ['uid' => QueryInterface::ORDER_ASCENDING];

    /**
     * Country-specific methods take precedence over the '' fallback as a group, same "specific
     * wins over fallback" precedence TaxRateRepository applies to a single winning rate.
     *
     * @return ShippingMethod[]
     */
    public function findApplicableForCountry(string $countryCode): array
    {
        $specific = $this->findByCountryCode($countryCode);
        return $specific !== [] ? $specific : $this->findByCountryCode('');
    }

    /**
     * @return ShippingMethod[]
     */
    private function findByCountryCode(string $countryCode): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('country', $countryCode));
        return $query->execute()->toArray();
    }
}
