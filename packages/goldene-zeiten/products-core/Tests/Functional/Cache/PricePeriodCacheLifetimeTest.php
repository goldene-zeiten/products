<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Cache;

use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Cache\CacheLifetimeCalculator;

/**
 * Tests CacheLifetimeCalculator integration with tx_products_domain_model_priceperiod.
 * Verifies that cache lifetime is capped by the valid_until (endtime) of active periods.
 */
final class PricePeriodCacheLifetimeTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    #[Test]
    public function activePeriodValidUntilCapsTheCacheLifetime(): void
    {
        $currentTime = time();
        $GLOBALS['ACCESS_TIME'] = $currentTime;

        $row = [
            'uid' => 1,
            'valid_from' => 0,
            'valid_until' => $currentTime + 600, // 10 minutes from now
        ];

        $lifetime = $this->get(CacheLifetimeCalculator::class)->calculateLifetimeForRow(
            'tx_products_domain_model_priceperiod',
            $row,
            86400
        );

        $this->assertLessThanOrEqual(600, $lifetime);
        $this->assertGreaterThan(0, $lifetime);
    }
}
