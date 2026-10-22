<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Catalog;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;

/**
 * Finds the products priced within a range: a product without variants by its own price, a product with
 * variants when any of its variants is within the range. Visibility is left to the query the uids are used in.
 */
final readonly class PriceRangeFilter
{
    private const PRODUCT_TABLE = 'tx_products_domain_model_product';
    private const ARTICLE_TABLE = 'tx_products_domain_model_article';

    public function __construct(
        private ConnectionPool $connectionPool
    ) {}

    /**
     * @param string|null $minPrice decimal string, no lower bound when null
     * @param string|null $maxPrice decimal string, no upper bound when null
     * @return int[]
     */
    public function findProductUids(?string $minPrice, ?string $maxPrice): array
    {
        $withoutVariants = $this->builder(self::PRODUCT_TABLE);
        $withoutVariants->select('uid')->from(self::PRODUCT_TABLE)
            ->where($withoutVariants->expr()->eq('articles', $withoutVariants->createNamedParameter(0, Connection::PARAM_INT)));
        $this->limitPrice($withoutVariants, $minPrice, $maxPrice);
        $withVariants = $this->builder(self::ARTICLE_TABLE);
        $withVariants->select('product')->from(self::ARTICLE_TABLE);
        $this->limitPrice($withVariants, $minPrice, $maxPrice);

        return array_values(array_unique([
            ...$this->column($withoutVariants, 'uid'),
            ...$this->column($withVariants, 'product'),
        ]));
    }

    private function builder(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(new DeletedRestriction());

        return $queryBuilder;
    }

    /**
     * The price is cast so it is compared as a number on every database, whatever the column type the
     * database made of the decimal field.
     */
    private function limitPrice(QueryBuilder $queryBuilder, ?string $minPrice, ?string $maxPrice): void
    {
        $price = 'CAST(' . $queryBuilder->quoteIdentifier('price') . ' AS DECIMAL(10,2))';
        if ($minPrice !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->comparison($price, '>=', $queryBuilder->createNamedParameter($minPrice)));
        }
        if ($maxPrice !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->comparison($price, '<=', $queryBuilder->createNamedParameter($maxPrice)));
        }
    }

    /**
     * @return int[]
     */
    private function column(QueryBuilder $queryBuilder, string $column): array
    {
        $result = $queryBuilder->executeQuery();
        $uids = [];
        while ($row = $result->fetchAssociative()) {
            $uids[] = (int)$row[$column];
        }

        return $uids;
    }
}
