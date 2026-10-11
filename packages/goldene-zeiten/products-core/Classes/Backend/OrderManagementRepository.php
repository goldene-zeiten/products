<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
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
        private readonly OrderAccessGuard $accessGuard,
    ) {}

    /**
     * Only orders on pages the backend user may read; all of them for an admin.
     *
     * @return array<int, array<string, mixed>>
     */
    public function fetchFiltered(OrderListFilter $filter, BackendUserAuthentication $backendUser): array
    {
        $queryBuilder = $this->baseQueryBuilder($backendUser);
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
    public function fetchRow(int $uid, BackendUserAuthentication $backendUser): ?array
    {
        $queryBuilder = $this->baseQueryBuilder($backendUser);
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

    private function baseQueryBuilder(BackendUserAuthentication $backendUser): QueryBuilder
    {
        $queryBuilder = $this->queryBuilderFor(self::TABLE)->select('*')->from(self::TABLE);
        if ($backendUser->isAdmin()) {
            return $queryBuilder;
        }
        $readablePids = $this->readablePids($backendUser);
        $queryBuilder->andWhere($readablePids === [] ? '1 = 0' : $queryBuilder->expr()->in(
            'pid',
            $queryBuilder->createNamedParameter($readablePids, Connection::PARAM_INT_ARRAY)
        ));
        return $queryBuilder;
    }

    /**
     * The storage pages holding orders that the user may read. Orders live on a handful of storage
     * folders, so checking each distinct pid is cheap.
     *
     * @return int[]
     */
    private function readablePids(BackendUserAuthentication $backendUser): array
    {
        $queryBuilder = $this->queryBuilderFor(self::TABLE);
        $result = $queryBuilder->select('pid')->distinct()->from(self::TABLE)->executeQuery();
        $readablePids = [];
        while ($row = $result->fetchAssociative()) {
            if ($this->accessGuard->mayRead((int)$row['pid'], $backendUser)) {
                $readablePids[] = (int)$row['pid'];
            }
        }
        return $readablePids;
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
            'deliveredAt' => $this->formatTimestamp((int)($row['delivered_at'] ?? 0)),
            'withdrawnAt' => $this->formatTimestamp((int)($row['withdrawn_at'] ?? 0)),
            'refundDueBy' => $this->formatRefundDeadline((int)($row['withdrawn_at'] ?? 0)),
            'returnProofAt' => $this->formatTimestamp((int)($row['return_proof_at'] ?? 0)),
            'returnedAt' => $this->formatTimestamp((int)($row['returned_at'] ?? 0)),
            'withdrawalLog' => $this->decodeLog((string)($row['withdrawal_log'] ?? '[]')),
        ];
    }

    /**
     * Art. 13 Directive 2011/83/EU: the refund is due within 14 days of being informed of the withdrawal.
     */
    private function formatRefundDeadline(int $withdrawnAt): ?string
    {
        return $withdrawnAt > 0 ? date('Y-m-d', strtotime('+14 days', $withdrawnAt) ?: $withdrawnAt) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeLog(string $json): array
    {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? array_values($decoded) : [];
    }

    private function formatTimestamp(int $timestamp): ?string
    {
        return $timestamp > 0 ? date('Y-m-d H:i', $timestamp) : null;
    }
}
