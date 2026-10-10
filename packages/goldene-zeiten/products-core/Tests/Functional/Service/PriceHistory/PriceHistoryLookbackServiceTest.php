<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\PriceHistory;

use GoldeneZeiten\Products\Core\Service\PriceHistory\PriceHistoryLookbackService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class PriceHistoryLookbackServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/price_history.csv');
    }

    /**
     * Prices compare as numbers: 9.99 is lower than 10.00, although "10.00" sorts first as text.
     */
    #[Test]
    public function theLowestPriceIsComparedNumerically(): void
    {
        $subject = $this->get(PriceHistoryLookbackService::class);

        $lowest = $subject->findLowestPriceSince(1, null, 0);

        $this->assertNotNull($lowest);
        $this->assertSame(999, $lowest->getCents());
    }
}
