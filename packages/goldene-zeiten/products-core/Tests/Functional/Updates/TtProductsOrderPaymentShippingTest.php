<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsOrderUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * tt_products stores the chosen payment and shipping as "<key>: <title>" text; the keys are the shop's own
 * TypoScript keys, so `migration.orderPaymentMethods` and `migration.orderShippingOptions` map them.
 * Checkout stubs tt_products creates when a checkout starts are no orders.
 */
final class TtProductsOrderPaymentShippingTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsOrderPaymentShippingTest/orders.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['orderPaymentMethods'] = '10:cashondelivery, 20:prepayment, 30:cash, 40:invoice, 90:paypal';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['orderShippingOptions'] = '10:tablerate:1, 20:pickup';
    }

    #[Test]
    #[DataProvider('mappingProvider')]
    public function theChosenPaymentAndShippingAreMapped(int $legacyUid, string $paymentMethod, string $shippingProvider, string $shippingOption, string $shippingLabel): void
    {
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();

        $row = $this->orderRow($legacyUid);
        $this->assertSame($paymentMethod, $row['payment_method']);
        $this->assertSame($shippingProvider, $row['shipping_provider']);
        $this->assertSame($shippingOption, $row['shipping_option']);
        $this->assertSame($shippingLabel, $row['shipping_label']);
    }

    public static function mappingProvider(): \Generator
    {
        yield 'cash with pick-up' => [
            'legacyUid' => 1,
            'paymentMethod' => 'cash',
            'shippingProvider' => 'pickup',
            'shippingOption' => '',
            'shippingLabel' => 'Selbstabholung (kostenfrei)',
        ];
        yield 'PayPal with delivery' => [
            'legacyUid' => 2,
            'paymentMethod' => 'paypal',
            'shippingProvider' => 'tablerate',
            'shippingOption' => '1',
            'shippingLabel' => 'Versand (kostenfrei)',
        ];
        yield 'an unmapped payment falls back to invoice, without shipping' => [
            'legacyUid' => 3,
            'paymentMethod' => 'invoice',
            'shippingProvider' => '',
            'shippingOption' => '',
            'shippingLabel' => '',
        ];
    }

    #[Test]
    public function anUnmappedPaymentIsReported(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringContainsString('sys_products_orders uid 3 used payment "77: Gone method", which migration.orderPaymentMethods does not map; it becomes "invoice".', $output->fetch());
    }

    #[Test]
    public function anAbandonedCheckoutIsSkippedAndReported(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $this->assertSame(0, $this->get(LegacyMigrationHelper::class)->resolveLocalUid('sys_products_orders', 4, 'tx_products_domain_model_order'));
        $this->assertStringContainsString('sys_products_orders uid 4 is an abandoned checkout without customer, amount and payment, skipped.', $output->fetch());
        $this->assertFalse($subject->updateNecessary());
    }

    /**
     * @return array<string, mixed>
     */
    private function orderRow(int $legacyUid): array
    {
        $localUid = $this->get(LegacyMigrationHelper::class)->resolveLocalUid('sys_products_orders', $legacyUid, 'tx_products_domain_model_order');
        $row = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_order')
            ->select(['*'], 'tx_products_domain_model_order', ['uid' => (int)$localUid])
            ->fetchAssociative();
        $this->assertIsArray($row);
        return $row;
    }
}
