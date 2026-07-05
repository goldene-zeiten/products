<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Order;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Service\Order\Exception\InsufficientStockException;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * A product with articles is purchasable only via one of its articles,
 * so stock is decremented on the article when one is given, else on the product.
 */
final class StockService
{
    private const PRODUCT_TABLE = 'tx_products_domain_model_product';
    private const ARTICLE_TABLE = 'tx_products_domain_model_article';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    public function decrementForItem(int $productUid, ?int $articleUid, int $quantity): void
    {
        if ($articleUid !== null) {
            $this->decrement(self::ARTICLE_TABLE, $articleUid, $quantity);
            return;
        }

        $this->decrement(self::PRODUCT_TABLE, $productUid, $quantity);
    }

    private function decrement(string $table, int $uid, int $quantity): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $affectedRows = $queryBuilder
            ->update($table)
            ->set('in_stock', $queryBuilder->quoteIdentifier('in_stock') . ' - ' . $queryBuilder->createNamedParameter($quantity, ParameterType::INTEGER), false)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)),
                $queryBuilder->expr()->gte('in_stock', $queryBuilder->createNamedParameter($quantity, ParameterType::INTEGER))
            )
            ->executeStatement();

        if ($affectedRows === 0) {
            throw new InsufficientStockException(
                sprintf('Insufficient stock for uid %d in table %s.', $uid, $table),
                1751751020
            );
        }
    }
}
