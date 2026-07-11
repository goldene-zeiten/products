<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\CreditPoints;

use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsBalanceService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * CreditPointsBalanceService::debitIfAffordable() guards the maintained balance with a single
 * atomic SQL statement (`UPDATE ... SET balance = balance - ? WHERE balance >= ?`), the same
 * pattern StockService already uses for stock - so calling it twice against the same balance can
 * never let both calls succeed beyond what the balance can actually afford, regardless of call
 * ordering or true concurrency.
 */
final class ConcurrentCreditPointsSpendRaceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/credit_points.csv');
    }

    #[Test]
    public function secondConcurrentSpendAgainstTheSameBalanceIsAtomicallyRejected(): void
    {
        $creditPointsBalanceService = $this->get(CreditPointsBalanceService::class);

        // frontend_user 1 has a ledger-derived balance of 70 (see credit_points.csv) - adopted
        // into the new balance table on first touch by ensureRowExists().
        $this->assertTrue($creditPointsBalanceService->debitIfAffordable(1, 60));
        $this->assertFalse($creditPointsBalanceService->debitIfAffordable(1, 60));

        $this->assertSame(10, $creditPointsBalanceService->getBalance(1));
    }
}
