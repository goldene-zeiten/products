<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * QueryBuilder reads for the backend order module; writes go through {@see OrderStatusWriter}.
 */
final class OrderManagementRepository
{
    private const TABLE = 'tx_products_domain_model_order';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchFiltered(OrderListFilter $filter): array
    {
        $queryBuilder = $this->baseQueryBuilder();
        $this->applyFilter($queryBuilder, $filter);
        $result = $queryBuilder->orderBy('order_date', 'DESC')->setMaxResults(100)->executeQuery();
        $orders = [];
        while ($row = $result->fetchAssociative()) {
            $orders[] = $this->mapRow($row);
        }
        return $orders;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function fetchRow(int $uid): ?array
    {
        $queryBuilder = $this->baseQueryBuilder();
        $row = $queryBuilder->andWhere($queryBuilder->expr()->eq(
            'uid',
            $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)
        ))->executeQuery()->fetchAssociative();
        return $row === false ? null : $this->mapRow($row);
    }

    private function queryBuilderFor(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder;
    }

    private function applyFilter(QueryBuilder $queryBuilder, OrderListFilter $filter): void
    {
        if ($filter->status !== null && $filter->status !== '') {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('status', $queryBuilder->createNamedParameter($filter->status)));
        }
        if ($filter->orderNumber !== null && $filter->orderNumber !== '') {
            $needle = '%' . $queryBuilder->escapeLikeWildcards($filter->orderNumber) . '%';
            $queryBuilder->andWhere($queryBuilder->expr()->like('order_number', $queryBuilder->createNamedParameter($needle)));
        }
        if ($filter->email !== null && $filter->email !== '') {
            $needle = '%' . $queryBuilder->escapeLikeWildcards($filter->email) . '%';
            $queryBuilder->andWhere($queryBuilder->expr()->like('email', $queryBuilder->createNamedParameter($needle)));
        }
        $this->applyDateRange($queryBuilder, $filter);
    }

    private function applyDateRange(QueryBuilder $queryBuilder, OrderListFilter $filter): void
    {
        if ($filter->dateFrom !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->gte(
                'order_date',
                $queryBuilder->createNamedParameter($filter->dateFrom->getTimestamp(), ParameterType::INTEGER)
            ));
        }
        if ($filter->dateTo !== null) {
            $queryBuilder->andWhere($queryBuilder->expr()->lte(
                'order_date',
                $queryBuilder->createNamedParameter($filter->dateTo->getTimestamp(), ParameterType::INTEGER)
            ));
        }
    }

    private function baseQueryBuilder(): QueryBuilder
    {
        return $this->queryBuilderFor(self::TABLE)->select('*')->from(self::TABLE);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapRow(array $row): array
    {
        return [
            'uid' => (int)$row['uid'],
            'orderNumber' => (string)$row['order_number'],
            'orderDate' => $this->formatTimestamp((int)($row['order_date'] ?? 0)),
            'email' => (string)$row['email'],
            'status' => (string)$row['status'],
            'paymentStatus' => (string)$row['payment_status'],
            'paymentMethod' => (string)$row['payment_method'],
            'totalGrossCents' => (int)$row['total_gross'],
            'discountTotalCents' => (int)($row['discount_total'] ?? 0),
            'shippingLabel' => (string)($row['shipping_label'] ?? ''),
            'shippingProvider' => (string)($row['shipping_provider'] ?? ''),
            'shippingTotalCents' => (int)($row['shipping_total'] ?? 0),
            'currency' => (string)$row['currency'],
            'customerNote' => (string)($row['customer_note'] ?? ''),
        ];
    }

    private function formatTimestamp(int $timestamp): ?string
    {
        return $timestamp > 0 ? date('Y-m-d H:i', $timestamp) : null;
    }
}
