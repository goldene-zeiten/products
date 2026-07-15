<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InsufficientStockException;
use TYPO3\CMS\Core\Database\ConnectionPool;

final class StockService
{
    private const PRODUCT_TABLE = 'tx_products_domain_model_product';
    private const ARTICLE_TABLE = 'tx_products_domain_model_article';

    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    /**
     * @return int the stock level after decrementing, for low-stock threshold checks at the call site
     */
    public function decrementForItem(int $productUid, ?int $articleUid, int $quantity): int
    {
        if ($articleUid !== null) {
            return $this->decrement(self::ARTICLE_TABLE, $articleUid, $quantity);
        }

        return $this->decrement(self::PRODUCT_TABLE, $productUid, $quantity);
    }

    private function decrement(string $table, int $uid, int $quantity): int
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

        return $this->currentStock($table, $uid);
    }

    private function currentStock(string $table, int $uid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        return (int)$queryBuilder
            ->select('in_stock')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchOne();
    }
}
