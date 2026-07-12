<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Voucher;

use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherNotApplicableException;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Atomic SQL UPDATE guards usage limits via {@see VoucherService::redeemAtomically()}.
 */
final class ConcurrentVoucherRedemptionRaceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Order/Fixtures/order_placement_with_voucher.csv');
    }

    #[Test]
    public function secondConcurrentRedemptionOfASingleUseVoucherIsAtomicallyRejected(): void
    {
        $voucherService = $this->get(VoucherService::class);
        $voucher = $this->get(VoucherRepository::class)->findOneByCode('ONETIME');
        $this->assertNotNull($voucher);

        $voucherService->redeemAtomically($voucher);

        try {
            $voucherService->redeemAtomically($voucher);
            $this->fail('Expected VoucherNotApplicableException was not thrown.');
        } catch (VoucherNotApplicableException $exception) {
            $this->assertSame(1751850004, $exception->getCode());
        }

        // Bypass Extbase identity map to verify raw SQL update.
        $this->assertSame(1, $this->currentRedemptionCount($voucher->getUid() ?? 0));
    }

    private function currentRedemptionCount(int $voucherUid): int
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_voucher');
        return (int)$queryBuilder
            ->select('redemption_count')
            ->from('tx_products_domain_model_voucher')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($voucherUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchOne();
    }
}
