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

    /**
     * `findByUid()` respects the TypoScript-configured storage page by default, which is never
     * set in a backend context - the backend order module needs this to fetch an order for
     * editing regardless of any frontend persistence configuration.
     */
    public function findByUidIgnoringStoragePage(int $uid): ?Order
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('uid', $uid));
        $result = $query->execute()->getFirst();
        return $result instanceof Order ? $result : null;
    }
}
