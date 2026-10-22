<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Model\Manufacturer;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;

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
     * without groups, every manufacturer with a visible product. Sorted by the brand name shown in the list,
     * following German collation.
     *
     * The manufacturer records are read from the configured storage folder; a caller without the TypoScript
     * of a rendered page names the folder in $storagePageIds.
     *
     * @param int[][] $categoryGroups
     * @param int[] $storagePageIds empty: the configured storage folder
     * @return list<Manufacturer>
     */
    public function findWithProductsInCategories(array $categoryGroups, array $storagePageIds = []): array
    {
        $query = $this->createQuery();
        if ($storagePageIds !== []) {
            $query->getQuerySettings()->setStoragePageIds($storagePageIds);
        }
        $uids = $this->manufacturerUids($categoryGroups) ?: [0];
        $query->matching($query->logicalOr($query->in('uid', $uids), $query->in('l10n_parent', $uids)));
        $manufacturers = $query->execute()->toArray();
        $collator = new \Collator('de_DE');
        usort($manufacturers, static fn(Manufacturer $a, Manufacturer $b): int => $collator->compare($a->getBrandName(), $b->getBrandName()) ?: $a->getUid() <=> $b->getUid());
        return $manufacturers;
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
