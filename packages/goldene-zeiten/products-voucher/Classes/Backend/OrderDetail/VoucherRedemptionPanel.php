<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Backend\OrderDetail;

use GoldeneZeiten\Products\Core\Backend\OrderDetail\OrderDetailPanelInterface;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\View\ViewFactoryData;
use TYPO3\CMS\Core\View\ViewFactoryInterface;

/**
 * The vouchers redeemed on an order and any voucher the order earned, shown as an order-detail panel in
 * the backend order module. The core reaches it only through the tagged order-detail-panel contract, so
 * it stays unaware that vouchers exist.
 *
 * Registered public in Services.yaml so a functional test can fetch it directly: its only other consumer
 * is the panel registry's tagged iterator, which inlines it and leaves no fetchable service otherwise.
 */
final readonly class VoucherRedemptionPanel implements OrderDetailPanelInterface
{
    private const REDEMPTION_TABLE = 'tx_products_domain_model_voucherredemption';
    private const VOUCHER_TABLE = 'tx_products_domain_model_voucher';

    public function __construct(
        private ConnectionPool $connectionPool,
        private ViewFactoryInterface $viewFactory,
    ) {}

    public function renderForOrder(int $orderUid): ?string
    {
        $redemptions = $this->fetchRedemptions($orderUid);
        $gainedVoucher = $this->fetchGainedVoucher($orderUid);
        if ($redemptions === [] && $gainedVoucher === null) {
            return null;
        }

        $view = $this->viewFactory->create(new ViewFactoryData(
            templateRootPaths: ['EXT:products_voucher/Resources/Private/Backend/Templates/'],
            partialRootPaths: ['EXT:products_voucher/Resources/Private/Backend/Partials/'],
        ));
        $view->assign('redemptions', $redemptions);
        $view->assign('gainedVoucher', $gainedVoucher);

        return $view->render('OrderDetail/VoucherRedemptions');
    }

    /**
     * @return array<int, array{voucherCode: string, discountTotalCents: int, redeemedAt: ?string}>
     */
    private function fetchRedemptions(int $orderUid): array
    {
        $queryBuilder = $this->queryBuilderFor(self::REDEMPTION_TABLE);
        $rows = $queryBuilder
            ->select('*')
            ->from(self::REDEMPTION_TABLE)
            ->where($queryBuilder->expr()->eq('order_uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAllAssociative();

        return array_map(
            static fn(array $row): array => [
                'voucherCode' => (string)$row['voucher_code'],
                'discountTotalCents' => (int)$row['discount_total'],
                'redeemedAt' => (int)($row['redeemed_at'] ?? 0) > 0 ? date('Y-m-d H:i', (int)$row['redeemed_at']) : null,
            ],
            $rows,
        );
    }

    /**
     * @return array{code: string, used: bool}|null
     */
    private function fetchGainedVoucher(int $orderUid): ?array
    {
        $queryBuilder = $this->queryBuilderFor(self::VOUCHER_TABLE);
        $row = $queryBuilder
            ->select('uid', 'code')
            ->from(self::VOUCHER_TABLE)
            ->where($queryBuilder->expr()->eq('generated_from_order', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false) {
            return null;
        }

        return [
            'code' => (string)$row['code'],
            'used' => $this->countRedemptionsFor((int)$row['uid']) > 0,
        ];
    }

    private function countRedemptionsFor(int $voucherUid): int
    {
        $queryBuilder = $this->queryBuilderFor(self::REDEMPTION_TABLE);

        return (int)$queryBuilder
            ->count('uid')
            ->from(self::REDEMPTION_TABLE)
            ->where($queryBuilder->expr()->eq('voucher_uid', $queryBuilder->createNamedParameter($voucherUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }

    private function queryBuilderFor(string $table): QueryBuilder
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder;
    }
}
