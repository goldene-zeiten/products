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

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool}>
     */
    public function searchCategories(string $query): array
    {
        $queryBuilder = $this->categoryQueryBuilder();
        $this->applySearch($queryBuilder, ['title', 'slug', 'description'], $query);
        return $this->fetchCategories($queryBuilder);
    }

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool, itemNumber: string}>
     */
    public function searchProducts(string $query): array
    {
        $queryBuilder = $this->productQueryBuilder();
        $this->applySearch(
            $queryBuilder,
            ['product.title', 'product.subtitle', 'product.slug', 'product.description', 'product.item_number', 'product.ean'],
            $query
        );
        return $this->fetchItems($queryBuilder);
    }

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool, itemNumber: string}>
     */
    public function searchArticles(string $query): array
    {
        $queryBuilder = $this->articleQueryBuilder();
        $this->applySearch($queryBuilder, ['article.title', 'article.item_number', 'article.ean'], $query);
        return $this->fetchItems($queryBuilder);
    }

    /**
     * @return array{uid: int, title: string, hidden: bool, itemNumber: string, product: int}|null
     */
    public function fetchArticleByUid(int $uid): ?array
    {
        $queryBuilder = $this->articleQueryBuilder();
        $queryBuilder->addSelect('article.product')
            ->andWhere($queryBuilder->expr()->eq(
                'article.uid',
                $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)
            ));
        $row = $queryBuilder->executeQuery()->fetchAssociative();
        if ($row === false) {
            return null;
        }
        return [
            'uid' => (int)$row['uid'],
            'title' => (string)$row['title'],
            'hidden' => (bool)$row['hidden'],
            'itemNumber' => (string)$row['item_number'],
            'product' => (int)$row['product'],
        ];
    }

    /**
     * First category of a product that is within the given mounts (or the first one at all when unrestricted).
     * @param int[]|null $mounts
     */
    public function fetchPrimaryCategoryUidOfProduct(int $productUid, ?array $mounts): ?int
    {
        $categoryUids = $this->fetchCategoryUidsOfProduct($productUid);
        if ($mounts === null) {
            return $categoryUids[0] ?? null;
        }
        foreach ($categoryUids as $categoryUid) {
            if (in_array($categoryUid, $mounts, true) || in_array($this->fetchParentCategoryUid($categoryUid), $mounts, true)) {
                return $categoryUid;
            }
        }
        return $categoryUids[0] ?? null;
    }

    /**
     * Ancestor category uids from root to (but excluding) the given category, root-first.
     * @return int[]
     */
    public function fetchCategoryAncestorChain(int $categoryUid): array
    {
        $chain = [];
        $current = $categoryUid;
        for ($depth = 0; $depth < 100; $depth++) {
            $parent = $this->fetchParentCategoryUid($current);
            if ($parent === null || $parent === 0) {
                break;
            }
            $chain[] = $parent;
            $current = $parent;
        }
        return array_reverse($chain);
    }

    /**
     * @return array<int, array{uid: int, title: string, hidden: bool, itemNumber: string}>
     */
    public function fetchAllProductsOrdered(): array
    {
        return $this->fetchItems($this->productQueryBuilder());
    }

    /**
     * Renumbers `sorting` for category siblings sharing $uid's parent, placing it directly
     * before $beforeUid (or last, when null). TYPO3's native sortby is pid-scoped only, and
     * every category here shares one flat storage-folder pid, so DataHandler's generic
     * cmd[move] can't express "reorder within this parent_category branch" - this does.
     */
    public function reorderCategorySiblings(int $uid, ?int $beforeUid): void
    {
        $parentUid = $this->fetchParentCategoryUid($uid) ?? 0;
        $siblingUids = array_map(static fn(array $c): int => $c['uid'], $this->fetchChildCategories($parentUid));
        $this->applyReorder(self::TABLE_CATEGORY, $siblingUids, $uid, $beforeUid);
    }

    /**
     * Same rationale as reorderCategorySiblings(), for the flat top-level product list.
     */
    public function reorderProducts(int $uid, ?int $beforeUid): void
    {
        $siblingUids = array_map(static fn(array $p): int => $p['uid'], $this->fetchAllProductsOrdered());
        $this->applyReorder(self::TABLE_PRODUCT, $siblingUids, $uid, $beforeUid);
    }

    /**
     * @param int[] $siblingUids current order, ascending
     */
    private function applyReorder(string $table, array $siblingUids, int $uid, ?int $beforeUid): void
    {
        $withoutMoved = array_values(array_filter($siblingUids, static fn(int $u): bool => $u !== $uid));
        $insertAt = $beforeUid === null ? false : array_search($beforeUid, $withoutMoved, true);
        array_splice($withoutMoved, $insertAt === false ? count($withoutMoved) : $insertAt, 0, [$uid]);
        foreach ($withoutMoved as $index => $siblingUid) {
            $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
            $queryBuilder->update($table)
                ->set('sorting', ($index + 1) * 2)
                ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($siblingUid, ParameterType::INTEGER)))
                ->executeStatement();
        }
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
            ->orderBy('sorting');
        return $queryBuilder;
    }

    private function productQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_PRODUCT);
        $this->applyRestrictions($queryBuilder);
        $queryBuilder->select('product.uid', 'product.title', 'product.hidden', 'product.item_number')
            ->from(self::TABLE_PRODUCT, 'product')
            ->andWhere($queryBuilder->expr()->eq('product.sys_language_uid', 0))
            ->orderBy('product.sorting');
        return $queryBuilder;
    }

    private function articleQueryBuilder(): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE_ARTICLE);
        $this->applyRestrictions($queryBuilder);
        $queryBuilder->select('article.uid', 'article.title', 'article.hidden', 'article.item_number')
            ->from(self::TABLE_ARTICLE, 'article')
            ->andWhere($queryBuilder->expr()->eq('article.sys_language_uid', 0))
            ->orderBy('article.sorting');
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
     * @param string[] $fields
     */
    private function applySearch(QueryBuilder $queryBuilder, array $fields, string $query): void
    {
        $needle = '%' . $queryBuilder->escapeLikeWildcards($query) . '%';
        $conditions = array_map(
            fn(string $field): string => $queryBuilder->expr()->like($field, $queryBuilder->createNamedParameter($needle)),
            $fields
        );
        $queryBuilder->andWhere($queryBuilder->expr()->or(...$conditions));
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
