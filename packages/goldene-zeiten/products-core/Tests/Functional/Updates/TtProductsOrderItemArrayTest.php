<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsOrderUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * tt_products 3.5 often writes no rows to sys_products_orders_mm_tt_products; the order lines then live
 * only in the serialized orderData, with the prices actually charged and the variant chosen.
 */
final class TtProductsOrderItemArrayTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    #[Test]
    public function theLinesOfTheOrderDataBecomeOrderItemsWithTheirChargedPrices(): void
    {
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsOrderItemArrayTest/orders.csv');
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsOrderItemArrayTest/items.csv');
    }
}
