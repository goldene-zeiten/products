<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\ShippingMethod;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * @extends AbstractReadOnlyRepository<ShippingMethod>
 */
final class ShippingMethodRepository extends AbstractReadOnlyRepository
{
    protected $defaultOrderings = ['uid' => QueryInterface::ORDER_ASCENDING];

    /**
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
