<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsOrderUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A legacy order line carries the values the customer chose in its product row; the option columns of
 * this extension turn them into the line's options, so the order still says which size was ordered.
 */
final class LegacyOrderLineOptionsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
        'goldene-zeiten/products-personalization',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/legacy_orders.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_personalization']['migration'] = [
            'optionColumns' => 'color:Ring size, color2:Bracelet length, size:Engraving font',
            'personalizingOptionColumns' => 'size',
            'optionNoneValues' => 'keine',
        ];
    }

    #[Test]
    #[DataProvider('orderProvider')]
    public function theChosenValuesBecomeTheLineOptions(int $legacyUid, string $expectedFont, WithdrawalExclusion $expectedExclusion): void
    {
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();

        $item = $this->orderItem($legacyUid);
        $options = $item->getLineOptions();
        $this->assertCount(2, $options);
        $this->assertSame('Ring size', $options[0]->getLabel());
        $this->assertSame('52', $options[0]->getValueLabel());
        $this->assertSame('Engraving font', $options[1]->getLabel());
        $this->assertSame($expectedFont, $options[1]->getValueLabel());
        $this->assertSame($expectedExclusion, $item->getWithdrawalExclusion());
    }

    public static function orderProvider(): \Generator
    {
        yield 'no engraving' => [
            'legacyUid' => 10,
            'expectedFont' => 'keine',
            'expectedExclusion' => WithdrawalExclusion::NONE,
        ];
        yield 'an engraving font' => [
            'legacyUid' => 11,
            'expectedFont' => 'Druckschrift',
            'expectedExclusion' => WithdrawalExclusion::PERSONALIZED,
        ];
    }

    private function orderItem(int $legacyUid): OrderItem
    {
        $orderUid = (int)$this->get(LegacyMigrationHelper::class)->resolveLocalUid('sys_products_orders', $legacyUid, 'tx_products_domain_model_order');
        $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage($orderUid);
        $this->assertInstanceOf(Order::class, $order);
        $item = $order->getItems()->toArray()[0] ?? null;
        $this->assertInstanceOf(OrderItem::class, $item);
        return $item;
    }
}
