<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Backend;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Database\Query\Restriction\WorkspaceRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Plain QueryBuilder access to the category/product/article tree for the backend module.
 * Deliberately not Extbase-based: backend modules are plain core, Extbase is a frontend concern.
 */
final class CategoryTreeRepository
{
    private const TABLE_CATEGORY = 'tx_products_domain_model_category';
    private const TABLE_PRODUCT = 'tx_products_domain_model_product';
    private const TABLE_ARTICLE = 'tx_products_domain_model_article';
    private const TABLE_PRODUCT_CATEGORY_MM = 'tx_products_product_category_mm';

    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool}>
     */
    public function fetchRootCategories(): array
    {
        $queryBuilder = $this->categoryQueryBuilder();
        $queryBuilder->andWhere($queryBuilder->expr()->eq(
            'parent_category',
            $queryBuilder->createNamedParameter(0, ParameterType::INTEGER)
        ));
        return $this->fetchCategories($queryBuilder);
    }

    /**
     * @param int[] $uids
     * @return array<int, array{uid: int, title: string, hidden: bool}>
     */
    public function fetchCategoriesByUids(array $uids): array
    {
        if ($uids === []) {
            return [];
        }
        $queryBuilder = $this->categoryQueryBuilder();
        $queryBuilder->andWhere($queryBuilder->expr()->in(
            'uid',
            $queryBuilder->createNamedParameter($uids, ArrayParameterType::INTEGER)
        ));
        return $this->fetchCategories($queryBuilder);
    }

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool}>
     */
    public function fetchChildCategories(int $parentUid): array
    {
        $queryBuilder = $this->categoryQueryBuilder();
        $queryBuilder->andWhere($queryBuilder->expr()->eq(
            'parent_category',
            $queryBuilder->createNamedParameter($parentUid, ParameterType::INTEGER)
        ));
        return $this->fetchCategories($queryBuilder);
    }

    public function categoryHasChildren(int $uid): bool
    {
        return $this->fetchChildCategories($uid) !== [];
    }

    /**
     * @return array{uid: int, title: string, hidden: bool}|null
     */
    public function fetchCategoryByUid(int $uid): ?array
    {
        return $this->fetchCategoriesByUids([$uid])[0] ?? null;
    }

    /**
     * @return array{uid: int, title: string, hidden: bool, itemNumber: string}|null
     */
    public function fetchProductByUid(int $uid): ?array
    {
        $queryBuilder = $this->productQueryBuilder();
        $queryBuilder->andWhere($queryBuilder->expr()->eq(
            'product.uid',
            $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)
        ));
        return $this->fetchItems($queryBuilder)[0] ?? null;
    }

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool, itemNumber: string}>
     */
    public function fetchProductsByCategory(int $categoryUid): array
    {
        $queryBuilder = $this->productQueryBuilder();
        $queryBuilder->join(
            'product',
            self::TABLE_PRODUCT_CATEGORY_MM,
            'mm',
            $queryBuilder->expr()->eq('mm.uid_local', $queryBuilder->quoteIdentifier('product.uid'))
        )->andWhere($queryBuilder->expr()->eq(
            'mm.uid_foreign',
            $queryBuilder->createNamedParameter($categoryUid, ParameterType::INTEGER)
        ));
        return $this->fetchItems($queryBuilder);
    }

    public function productHasArticles(int $uid): bool
    {
        return $this->fetchArticlesByProduct($uid) !== [];
    }

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool, itemNumber: string}>
     */
    public function fetchArticlesByProduct(int $productUid): array
    {
        $queryBuilder = $this->articleQueryBuilder();
        $queryBuilder->andWhere($queryBuilder->expr()->eq(
            'article.product',
            $queryBuilder->createNamedParameter($productUid, ParameterType::INTEGER)
        ));
        return $this->fetchItems($queryBuilder);
    }

    /**
     * @return int[]
     */
    public function fetchCategoryUidsOfProduct(int $productUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PRODUCT_CATEGORY_MM);
        $rows = $queryBuilder->select('uid_foreign')
            ->from(self::TABLE_PRODUCT_CATEGORY_MM)
            ->where($queryBuilder->expr()->eq(
                'uid_local',
                $queryBuilder->createNamedParameter($productUid, ParameterType::INTEGER)
            ))
            ->executeQuery()
            ->fetchFirstColumn();
        return array_map(intval(...), $rows);
    }

    public function fetchParentCategoryUid(int $categoryUid): ?int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CATEGORY);
        $this->applyRestrictions($queryBuilder);
        $parent = $queryBuilder->select('parent_category')
            ->from(self::TABLE_CATEGORY)
            ->where($queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter($categoryUid, ParameterType::INTEGER)
            ))
            ->executeQuery()
            ->fetchOne();
        return $parent === false ? null : (int)$parent;
    }

    public function categoryExists(int $uid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CATEGORY);
        $this->applyRestrictions($queryBuilder);
        $count = $queryBuilder->count('uid')
            ->from(self::TABLE_CATEGORY)
            ->where($queryBuilder->expr()->eq(
                'uid',
                $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)
            ))
            ->executeQuery()
            ->fetchOne();
        return (int)$count > 0;
    }

    private function categoryQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_CATEGORY);
        $this->applyRestrictions($queryBuilder);
        $queryBuilder->select('uid', 'title', 'hidden')
            ->from(self::TABLE_CATEGORY)
            ->andWhere($queryBuilder->expr()->eq('sys_language_uid', 0))
            ->orderBy('title');
        return $queryBuilder;
    }

    private function productQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PRODUCT);
        $this->applyRestrictions($queryBuilder);
        $queryBuilder->select('product.uid', 'product.title', 'product.hidden', 'product.item_number')
            ->from(self::TABLE_PRODUCT, 'product')
            ->andWhere($queryBuilder->expr()->eq('product.sys_language_uid', 0))
            ->orderBy('product.title');
        return $queryBuilder;
    }

    private function articleQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_ARTICLE);
        $this->applyRestrictions($queryBuilder);
        $queryBuilder->select('article.uid', 'article.title', 'article.hidden', 'article.item_number')
            ->from(self::TABLE_ARTICLE, 'article')
            ->andWhere($queryBuilder->expr()->eq('article.sys_language_uid', 0))
            ->orderBy('article.title');
        return $queryBuilder;
    }

    /**
     * Hidden records stay visible (this is a management view, like the page tree), but
     * deleted records and other workspace versions of a record are always excluded.
     */
    private function applyRestrictions(QueryBuilder $queryBuilder): void
    {
        $queryBuilder->getRestrictions()->removeAll()
            ->add(GeneralUtility::makeInstance(DeletedRestriction::class))
            ->add(GeneralUtility::makeInstance(WorkspaceRestriction::class, $this->getBackendUser()->workspace));
    }

    private function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool}>
     */
    private function fetchCategories(QueryBuilder $queryBuilder): array
    {
        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();
        return array_map(
            static fn(array $row): array => ['uid' => (int)$row['uid'], 'title' => (string)$row['title'], 'hidden' => (bool)$row['hidden']],
            $rows
        );
    }

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool, itemNumber: string}>
     */
    private function fetchItems(QueryBuilder $queryBuilder): array
    {
        $rows = $queryBuilder->executeQuery()->fetchAllAssociative();
        return array_map(
            static fn(array $row): array => [
                'uid' => (int)$row['uid'],
                'title' => (string)$row['title'],
                'hidden' => (bool)$row['hidden'],
                'itemNumber' => (string)$row['item_number'],
            ],
            $rows
        );
    }
}
