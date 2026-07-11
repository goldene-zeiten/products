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
 * VoucherService::redeemAtomically() guards a voucher's usage limit with a single atomic SQL
 * statement (`UPDATE ... SET redemption_count = redemption_count + 1 WHERE redemption_count <
 * usage_limit`), the same pattern StockService already uses for stock - so, unlike the
 * count-then-insert sequence this replaced, calling it twice for a single-use voucher can never
 * let both calls succeed, regardless of call ordering or true concurrency.
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

        // Read the counter directly via the database, not through Extbase's identity map (which
        // would still show the object's originally-fetched value, not the raw SQL update above).
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
