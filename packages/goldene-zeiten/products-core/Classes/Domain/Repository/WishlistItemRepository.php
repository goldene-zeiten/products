<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\WishlistItem;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<WishlistItem>
 */
final class WishlistItemRepository extends Repository
{
    /**
     * @return QueryResultInterface<int, WishlistItem>
     */
    public function findByFrontendUser(int $frontendUser): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('frontendUser', $frontendUser));
        $query->setOrderings([
            'sorting' => QueryInterface::ORDER_ASCENDING,
            'created' => QueryInterface::ORDER_ASCENDING,
        ]);
        return $query->execute();
    }

    public function findOneByFrontendUserAndProduct(int $frontendUser, int $productUid): ?WishlistItem
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->logicalAnd(
            $query->equals('frontendUser', $frontendUser),
            $query->equals('product', $productUid)
        ));
        $result = $query->execute()->getFirst();
        return $result instanceof WishlistItem ? $result : null;
    }
}
