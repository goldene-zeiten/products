<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Repository;

use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListCriteria;
use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
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
    private const SEARCHABLE_COLUMNS = ['title', 'subtitle', 'item_number', 'description', 'ean'];

    /**
     * "uid" breaks ties for rows sharing the same "sorting" value.
     *
     * @var array<non-empty-string, QueryInterface::ORDER_*>
     */
    protected $defaultOrderings = [
        'sorting' => QueryInterface::ORDER_ASCENDING,
        'uid' => QueryInterface::ORDER_ASCENDING,
    ];

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {
        parent::__construct();
    }

    /**
     * All products across all storage pages.
     *
     * @return Product[]
     */
    public function findAllIgnoringStoragePage(): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        return $query->execute()->toArray();
    }

    /**
     * Direct members only, not the whole subtree beneath the category.
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
     * Direct members only, same scope as {@see self::findByCategory()}.
     */
    public function countByCategory(Category $category): int
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->contains('categories', $category));
        return $query->execute()->count();
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

    /**
     * Every product matching the term, for a paginator to slice.
     *
     * @return QueryResultInterface<int, Product>
     */
    public function findBySearchTerm(string $term, ?int $categoryUid): QueryResultInterface
    {
        return $this->buildSearchQuery($term, $categoryUid)->execute();
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
     * Matches the term through a plain query, because Extbase's like() declares no ESCAPE character and SQLite
     * has no default one - the escaped wildcards would never match there. The query states the escape
     * character itself, so "_" and "%" in a term match literally on every DBMS. Visibility and language are
     * left to the Extbase query this constraint is part of.
     *
     * @param QueryInterface<Product> $query
     */
    private function termConstraint(QueryInterface $query, string $term): ConstraintInterface
    {
        $productUids = $this->uidsMatchingTerm($term);

        return $productUids === [] ? $query->equals('uid', 0) : $query->in('uid', $productUids);
    }

    /**
     * @return int[]
     */
    private function uidsMatchingTerm(string $term): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $pattern = $queryBuilder->createNamedParameter('%' . $this->escapeLikeWildcards($term) . '%', Connection::PARAM_STR);
        $likeConstraints = array_map(
            static fn(string $column): string => $queryBuilder->expr()->like($column, $pattern, '\\'),
            self::SEARCHABLE_COLUMNS
        );
        $result = $queryBuilder
            ->select('uid')
            ->from('tx_products_domain_model_product')
            ->where($queryBuilder->expr()->or(...$likeConstraints))
            ->executeQuery();
        $productUids = [];
        while ($row = $result->fetchAssociative()) {
            $productUids[] = (int)$row['uid'];
        }

        return $productUids;
    }

    private function escapeLikeWildcards(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /**
     * @return Product[]
     */
    public function findOffers(): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('isOffer', true));
        return $query->execute()->toArray();
    }

    /**
     * @return Product[]
     */
    public function findHighlights(): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $query->matching($query->equals('isHighlight', true));
        return $query->execute()->toArray();
    }

    /**
     * Products created within the last $days days (inclusive), newest first.
     *
     * @return Product[]
     */
    public function findNew(int $days): array
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $cutoff = new \DateTime(sprintf('-%d days', $days));
        $query->matching($query->greaterThanOrEqual('crdate', $cutoff));
        $query->setOrderings(['crdate' => QueryInterface::ORDER_DESCENDING]);
        return $query->execute()->toArray();
    }

    /**
     * The query behind a product list. The "all" listing respects the configured storage page, like it
     * always did; the other listings span every storage page.
     *
     * @return QueryInterface<Product>
     */
    public function createListQuery(ProductListCriteria $criteria): QueryInterface
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage($criteria->mode === 'all' && $criteria->respectStoragePage);
        $constraints = $this->modeConstraints($query, $criteria);
        if ($criteria->productUids !== []) {
            $constraints[] = $query->in('uid', $criteria->productUids);
        }
        foreach (array_filter([$criteria->categoryUids, ...$criteria->requiredCategoryGroups]) as $categoryUids) {
            $constraints[] = $query->logicalOr(...array_map(
                static fn(int $categoryUid): ConstraintInterface => $query->contains('categories', $categoryUid),
                $categoryUids
            ));
        }
        if ($constraints !== []) {
            $query->matching($query->logicalAnd(...$constraints));
        }
        $query->setOrderings($this->listOrderings($criteria));
        return $query;
    }

    /**
     * @param QueryInterface<Product> $query
     * @return ConstraintInterface[]
     */
    private function modeConstraints(QueryInterface $query, ProductListCriteria $criteria): array
    {
        return match ($criteria->mode) {
            'offers' => [$query->equals('isOffer', true)],
            'highlights' => [$query->equals('isHighlight', true)],
            'new' => [$query->greaterThanOrEqual('crdate', new \DateTime(sprintf('-%d days', $criteria->newItemDays)))],
            'articles' => [$query->greaterThan('articles', 0)],
            default => [],
        };
    }

    /**
     * "new" lists the newest products first unless the element asks for another order.
     *
     * @return array<non-empty-string, QueryInterface::ORDER_*>
     */
    private function listOrderings(ProductListCriteria $criteria): array
    {
        $sorting = $criteria->sorting === ProductListCriteria::SORTING_MANUAL && $criteria->mode === 'new'
            ? ProductListCriteria::SORTING_NEWEST
            : $criteria->sorting;
        $primary = match ($sorting) {
            ProductListCriteria::SORTING_TITLE => ['title' => QueryInterface::ORDER_ASCENDING],
            ProductListCriteria::SORTING_PRICE_ASC => ['price' => QueryInterface::ORDER_ASCENDING],
            ProductListCriteria::SORTING_PRICE_DESC => ['price' => QueryInterface::ORDER_DESCENDING],
            ProductListCriteria::SORTING_NEWEST => ['crdate' => QueryInterface::ORDER_DESCENDING],
            default => ['sorting' => QueryInterface::ORDER_ASCENDING],
        };
        return $primary + ['uid' => QueryInterface::ORDER_ASCENDING];
    }
}
