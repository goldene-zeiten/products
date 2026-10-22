<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\Manufacturer;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * @extends AbstractReadOnlyRepository<Manufacturer>
 */
final class ManufacturerRepository extends AbstractReadOnlyRepository
{
    /**
     * @var array<non-empty-string, QueryInterface::ORDER_*>
     */
    protected $defaultOrderings = [
        'name' => QueryInterface::ORDER_ASCENDING,
        'uid' => QueryInterface::ORDER_ASCENDING,
    ];

    public function __construct(private readonly ConnectionPool $connectionPool)
    {
        parent::__construct();
    }

    /**
     * The visible manufacturers with a visible product that is in at least one category of every group;
     * without groups, every manufacturer with a visible product.
     *
     * @param int[][] $categoryGroups
     * @return QueryResultInterface<int, Manufacturer>
     */
    public function findWithProductsInCategories(array $categoryGroups): QueryResultInterface
    {
        $query = $this->createQuery();
        $query->matching($query->in('uid', $this->manufacturerUids($categoryGroups) ?: [0]));
        return $query->execute();
    }

    /**
     * @param int[][] $categoryGroups
     * @return int[]
     */
    private function manufacturerUids(array $categoryGroups): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->selectLiteral('DISTINCT ' . $queryBuilder->quoteIdentifier('manufacturer'))
            ->from('tx_products_domain_model_product')
            ->where($queryBuilder->expr()->gt('manufacturer', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)));
        foreach (array_filter($categoryGroups) as $categoryUids) {
            $inCategory = $this->connectionPool->getQueryBuilderForTable('tx_products_product_category_mm');
            $inCategory->select('uid_local')
                ->from('tx_products_product_category_mm')
                ->where($inCategory->expr()->in('uid_foreign', $queryBuilder->createNamedParameter($categoryUids, Connection::PARAM_INT_ARRAY)));
            $queryBuilder->andWhere($queryBuilder->expr()->in('uid', $inCategory->getSQL()));
        }
        $result = $queryBuilder->executeQuery();
        $uids = [];
        while ($row = $result->fetchAssociative()) {
            $uids[] = (int)$row['manufacturer'];
        }
        return $uids;
    }
}
