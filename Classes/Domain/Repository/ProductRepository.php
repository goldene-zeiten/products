<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;
use TYPO3\CMS\Extbase\Persistence\Generic\Qom\ConstraintInterface;
use TYPO3\CMS\Extbase\Persistence\QueryInterface;
use TYPO3\CMS\Extbase\Persistence\QueryResultInterface;

/**
 * @extends AbstractReadOnlyRepository<Product>
 */
final class ProductRepository extends AbstractReadOnlyRepository
{
    /**
     * @var string[]
     */
    private const SEARCHABLE_PROPERTIES = ['title', 'itemNumber', 'description', 'ean'];

    /**
     * Without an explicit ORDER BY, row order is undefined and differs across DBMS/versions
     * (observed to differ between PostgreSQL versions). "sorting" is the TCA-editable backend
     * order; "uid" breaks ties deterministically for rows sharing the same sorting value.
     *
     * @var array<non-empty-string, QueryInterface::ORDER_*>
     */
    protected $defaultOrderings = [
        'sorting' => QueryInterface::ORDER_ASCENDING,
        'uid' => QueryInterface::ORDER_ASCENDING,
    ];

    /**
     * Direct members only - a category listing shows what is assigned to that exact category,
     * not everything beneath it in the tree as well.
     *
     * @return Product[]
     */
    public function findByCategory(Category $category): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->contains('categories', $category));
        return $query->execute()->toArray();
    }

    /**
     * @return QueryResultInterface<int, Product>
     */
    public function search(string $term, ?int $categoryUid, int $offset, int $limit): QueryResultInterface
    {
        $query = $this->buildSearchQuery($term, $categoryUid);
        $query->setOffset($offset);
        $query->setLimit($limit);
        return $query->execute();
    }

    public function countSearchResults(string $term, ?int $categoryUid): int
    {
        return $this->buildSearchQuery($term, $categoryUid)->execute()->count();
    }

    /**
     * @return QueryInterface<Product>
     */
    private function buildSearchQuery(string $term, ?int $categoryUid): QueryInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $constraints = [$this->termConstraint($query, $term)];
        if ($categoryUid !== null) {
            $constraints[] = $query->contains('categories', $categoryUid);
        }
        $query->matching($query->logicalAnd(...$constraints));
        return $query;
    }

    /**
     * @param QueryInterface<Product> $query
     */
    private function termConstraint(QueryInterface $query, string $term): ConstraintInterface
    {
        $pattern = '%' . $this->escapeLikeWildcards($term) . '%';
        $likeConstraints = array_map(
            static fn(string $property): ConstraintInterface => $query->like($property, $pattern),
            self::SEARCHABLE_PROPERTIES
        );
        return $query->logicalOr(...$likeConstraints);
    }

    private function escapeLikeWildcards(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term);
    }
}
