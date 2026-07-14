<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\HandlingFee;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

/**
 * @extends AbstractReadOnlyRepository<HandlingFee>
 */
final class HandlingFeeRepository extends AbstractReadOnlyRepository
{
    protected $defaultOrderings = ['uid' => QueryInterface::ORDER_ASCENDING];

    /**
     * @return HandlingFee[]
     */
    public function findApplicableForCountry(string $countryCode): array
    {
        $specific = $this->findByCountryCode($countryCode);
        return $specific !== [] ? $specific : $this->findByCountryCode('');
    }

    /**
     * @return HandlingFee[]
     */
    private function findByCountryCode(string $countryCode): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('country', $countryCode));
        return $query->execute()->toArray();
    }
}
