<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Service;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Undoes what an order did with vouchers once it is cancelled: the vouchers it redeemed become usable
 * again, and the reward voucher it earned is withdrawn while nobody has used it. Running it again for the
 * same order changes nothing.
 */
final class VoucherRevocationService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool
    ) {}

    public function revokeForOrder(int $orderUid): void
    {
        foreach ($this->openRedemptions($orderUid) as $redemptionUid => $voucherUid) {
            // Only the request that marks the redemption reverted gives the use back, so it is given back once.
            if ($this->markReverted($redemptionUid)) {
                $this->releaseUse($voucherUid);
            }
        }
        $this->withdrawUnusedReward($orderUid);
    }

    /**
     * @return array<int, int> voucher uid by redemption uid
     */
    private function openRedemptions(int $orderUid): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_voucherredemption');
        $result = $queryBuilder
            ->select('uid', 'voucher_uid')
            ->from('tx_products_domain_model_voucherredemption')
            ->where(
                $queryBuilder->expr()->eq('order_uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('reverted_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery();
        $redemptions = [];
        while ($row = $result->fetchAssociative()) {
            $redemptions[(int)$row['uid']] = (int)$row['voucher_uid'];
        }
        return $redemptions;
    }

    private function markReverted(int $redemptionUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_voucherredemption');
        return $queryBuilder
            ->update('tx_products_domain_model_voucherredemption')
            ->set('reverted_at', $queryBuilder->createNamedParameter(time(), Connection::PARAM_INT), false)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($redemptionUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('reverted_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeStatement() === 1;
    }

    private function releaseUse(int $voucherUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_voucher');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update('tx_products_domain_model_voucher')
            ->set('redemption_count', $queryBuilder->quoteIdentifier('redemption_count') . ' - 1', false)
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($voucherUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->gt('redemption_count', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeStatement();
    }

    private function withdrawUnusedReward(int $orderUid): void
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_voucher');
        $queryBuilder->getRestrictions()->removeAll();
        $queryBuilder
            ->update('tx_products_domain_model_voucher')
            ->set('hidden', $queryBuilder->createNamedParameter(1, Connection::PARAM_INT), false)
            ->where(
                $queryBuilder->expr()->eq('generated_from_order', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('redemption_count', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeStatement();
    }
}
