<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Order;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InsufficientStockException;
use TYPO3\CMS\Core\Database\Connection;
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

    /**
     * Puts the items of an order that never left the warehouse back into stock - for an order cancelled
     * before shipping. Items already put back, e.g. by a partial withdrawal, are skipped.
     */
    public function releaseForOrder(int $orderUid): void
    {
        $this->release($orderUid, [], false);
    }

    /**
     * Puts the withdrawn items of an order back into stock - for goods the customer sent back after
     * withdrawing from a shipped order. Items already put back are skipped.
     */
    public function releaseWithdrawnForOrder(int $orderUid): void
    {
        $this->release($orderUid, [], true);
    }

    /**
     * Puts specific order items back into stock - for a partial withdrawal from an unshipped order.
     *
     * @param int[] $itemUids
     */
    public function releaseItems(array $itemUids): void
    {
        if ($itemUids === []) {
            return;
        }
        $this->release(0, $itemUids, false);
    }

    /**
     * Lines of unlimited products or articles were never taken out of stock, so they are not returned,
     * but still marked as processed.
     *
     * @param int[] $itemUids
     */
    private function release(int $orderUid, array $itemUids, bool $onlyWithdrawn): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_orderitem');
        $queryBuilder
            ->select('uid', 'product', 'article', 'quantity')
            ->from('tx_products_domain_model_orderitem')
            ->where($queryBuilder->expr()->eq('restocked_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)));
        if ($orderUid > 0) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('parent_order', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)));
        }
        if ($itemUids !== []) {
            $queryBuilder->andWhere($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($itemUids, Connection::PARAM_INT_ARRAY)));
        }
        if ($onlyWithdrawn) {
            $queryBuilder->andWhere($queryBuilder->expr()->gt('withdrawn_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)));
        }
        $result = $queryBuilder->executeQuery();
        $processed = [];
        while ($row = $result->fetchAssociative()) {
            $this->putBackLine((int)$row['product'], (int)$row['article'], (int)$row['quantity']);
            $processed[] = (int)$row['uid'];
        }
        $this->markRestocked($processed);
    }

    private function putBackLine(int $productUid, int $articleUid, int $quantity): void
    {
        if ($this->isUnlimited(self::PRODUCT_TABLE, $productUid) || ($articleUid > 0 && $this->isUnlimited(self::ARTICLE_TABLE, $articleUid))) {
            return;
        }
        $articleUid > 0
            ? $this->increment(self::ARTICLE_TABLE, $articleUid, $quantity)
            : $this->increment(self::PRODUCT_TABLE, $productUid, $quantity);
    }

    /**
     * @param int[] $itemUids
     */
    private function markRestocked(array $itemUids): void
    {
        if ($itemUids === []) {
            return;
        }
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_orderitem');
        $queryBuilder
            ->update('tx_products_domain_model_orderitem')
            ->set('restocked_at', time())
            ->where($queryBuilder->expr()->in('uid', $queryBuilder->createNamedParameter($itemUids, Connection::PARAM_INT_ARRAY)))
            ->executeStatement();
    }

    private function increment(string $table, int $uid, int $quantity): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update($table)
            ->set('in_stock', $queryBuilder->quoteIdentifier('in_stock') . ' + ' . $queryBuilder->createNamedParameter($quantity, Connection::PARAM_INT), false)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeStatement();
    }

    private function isUnlimited(string $table, int $uid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (bool)$queryBuilder
            ->select('unlimited_stock')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
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
