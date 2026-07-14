<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * QueryBuilder reads for the backend order module, with Extbase persistence for editing.
 */
final class OrderManagementRepository
{
    private const TABLE = 'tx_products_domain_model_order';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly OrderRepository $orderRepository,
        private readonly PersistenceManagerInterface $persistenceManager,
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchFiltered(OrderListFilter $filter): array
    {
        $queryBuilder = $this->baseQueryBuilder();
        $this->applyFilter($queryBuilder, $filter);
        $rows = $queryBuilder->orderBy('order_date', 'DESC')->setMaxResults(100)->executeQuery()->fetchAllAssociative();
        return array_map($this->mapRow(...), $rows);
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

    public function findForEditing(int $uid): ?Order
    {
        return $this->orderRepository->findByUidIgnoringStoragePage($uid);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchVoucherRedemptions(int $orderUid): array
    {
        $queryBuilder = $this->queryBuilderFor('tx_products_domain_model_voucherredemption');
        $rows = $queryBuilder->select('*')
            ->from('tx_products_domain_model_voucherredemption')
            ->where($queryBuilder->expr()->eq('order_uid', $queryBuilder->createNamedParameter($orderUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAllAssociative();
        return array_map($this->mapVoucherRedemptionRow(...), $rows);
    }

    /**
     * @return array{code: string, used: bool}|null
     */
    public function fetchGainedVoucher(int $orderUid): ?array
    {
        $queryBuilder = $this->queryBuilderFor('tx_products_domain_model_voucher');
        $row = $queryBuilder->select('uid', 'code')
            ->from('tx_products_domain_model_voucher')
            ->where($queryBuilder->expr()->eq('generated_from_order', $queryBuilder->createNamedParameter($orderUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) {
            return null;
        }
        return ['code' => (string)$row['code'], 'used' => $this->countRedemptionsFor((int)$row['uid']) > 0];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetchCreditPointsLedger(int $orderUid): array
    {
        $queryBuilder = $this->queryBuilderFor('tx_products_domain_model_creditpointstransaction');
        $rows = $queryBuilder->select('*')
            ->from('tx_products_domain_model_creditpointstransaction')
            ->where($queryBuilder->expr()->eq('order_uid', $queryBuilder->createNamedParameter($orderUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchAllAssociative();
        return array_map($this->mapCreditPointsRow(...), $rows);
    }

    private function countRedemptionsFor(int $voucherUid): int
    {
        $queryBuilder = $this->queryBuilderFor('tx_products_domain_model_voucherredemption');
        return (int)$queryBuilder->count('uid')
            ->from('tx_products_domain_model_voucherredemption')
            ->where($queryBuilder->expr()->eq('voucher_uid', $queryBuilder->createNamedParameter($voucherUid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchOne();
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapVoucherRedemptionRow(array $row): array
    {
        return [
            'voucherCode' => (string)$row['voucher_code'],
            'discountTotalCents' => (int)$row['discount_total'],
            'redeemedAt' => $this->formatTimestamp((int)($row['redeemed_at'] ?? 0)),
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function mapCreditPointsRow(array $row): array
    {
        return [
            'frontendUser' => (int)$row['frontend_user'],
            'points' => (int)$row['points'],
            'type' => (string)$row['type'],
            'created' => $this->formatTimestamp((int)($row['created'] ?? 0)),
        ];
    }

    private function queryBuilderFor(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return $queryBuilder;
    }

    /**
     * Explicitly update() before persistAll() since the order was fetched, not add()'d.
     */
    public function persist(Order $order): void
    {
        $this->orderRepository->update($order);
        $this->persistenceManager->persistAll();
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
            'voucherCodes' => json_decode((string)($row['voucher_codes'] ?? ''), true) ?: [],
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
