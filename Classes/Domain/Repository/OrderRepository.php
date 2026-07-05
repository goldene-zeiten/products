<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Order;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<Order>
 */
final class OrderRepository extends Repository
{
    /**
     * @return QueryResultInterface<Order>
     */
    public function findByFrontendUser(int $frontendUser): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('frontendUser', $frontendUser));
        $query->setOrderings(['orderDate' => QueryInterface::ORDER_DESCENDING]);
        return $query->execute();
    }
}
