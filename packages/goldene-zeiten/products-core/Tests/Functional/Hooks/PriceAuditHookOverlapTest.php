<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Hooks;

use GoldeneZeiten\Products\Core\Domain\Validation\PricePeriodOverlapGuard;
use GoldeneZeiten\Products\Core\Exception\PricePeriodOverlapException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Tests PricePeriodOverlapGuard integration via PriceAuditHook.
 * Verifies that overlapping price periods are rejected based on fe_group scope.
 */
final class PriceAuditHookOverlapTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PriceAuditHookOverlapTest/price_periods.csv');
    }

    #[Test]
    public function overlappingPublicPeriodForSameProductIsRejected(): void
    {
        $this->expectException(PricePeriodOverlapException::class);

        $this->get(PricePeriodOverlapGuard::class)->assertNoOverlap(
            [
                'product' => 1,
                'article' => 0,
                'fe_group' => 0,
                'valid_from' => 960000000,
                'valid_until' => 990000000,
            ],
            'NEW123'
        );
    }

    #[Test]
    public function overlappingWindowInDifferentFeGroupIsAllowed(): void
    {
        $result = $this->get(PricePeriodOverlapGuard::class)->assertNoOverlap(
            [
                'product' => 1,
                'article' => 0,
                'fe_group' => 2,
                'valid_from' => 960000000,
                'valid_until' => 990000000,
            ],
            'NEW456'
        );

        $this->assertSame(2, (int)$result['fe_group']);
    }

    #[Test]
    public function overlappingWindowInSameFeGroupAsExistingResellerPeriodIsRejected(): void
    {
        $this->expectException(PricePeriodOverlapException::class);

        $this->get(PricePeriodOverlapGuard::class)->assertNoOverlap(
            [
                'product' => 1,
                'article' => 0,
                'fe_group' => 1,
                'valid_from' => 960000000,
                'valid_until' => 990000000,
            ],
            'NEW789'
        );
    }

    #[Test]
    public function nonOverlappingWindowInSameScopeIsAllowed(): void
    {
        $result = $this->get(PricePeriodOverlapGuard::class)->assertNoOverlap(
            [
                'product' => 1,
                'article' => 0,
                'fe_group' => 0,
                'valid_from' => 978307201,
                'valid_until' => 1000000000,
            ],
            'NEW999'
        );

        $this->assertSame(1, (int)$result['product']);
    }
}
