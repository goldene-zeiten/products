<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsOrderUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsOrderUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'sys_products_orders';
    private const LOCAL_TABLE = 'tx_products_domain_model_order';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsOrderUpgradeWizardTest/sys_products_orders.csv');
    }

    private function subject(BufferedOutput $output): TtProductsOrderUpgradeWizard
    {
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput($output);
        return $subject;
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function deletedOrderIsNeverMigrated(): void
    {
        $subject = $this->subject(new BufferedOutput());
        $migrationHelper = $this->get(LegacyMigrationHelper::class);

        $subject->executeUpdate();

        $this->assertNull($migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 3, self::LOCAL_TABLE));
    }

    #[Test]
    public function normalOrderIsMigratedWithResolvedStatusAndCountry(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/orders_migrated.csv');
    }

    #[Test]
    public function billingAddressIsCreatedAndLinked(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/order_addresses_migrated.csv');
    }

    #[Test]
    public function parsedOrderDataIsStoredAsJson(): void
    {
        $subject = $this->subject(new BufferedOutput());
        $migrationHelper = $this->get(LegacyMigrationHelper::class);

        $subject->executeUpdate();

        $orderUid = (int)$migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);
        $legacyOrderData = json_decode($order['legacy_order_data'], true);

        $this->assertSame('1.2.3.4', $legacyOrderData['client_ip']);
        $this->assertSame(['version' => '1.0'], $legacyOrderData['order_data']);
    }

    #[Test]
    public function unknownStatusUnrecognizedCountryAndUnparseableBlobAreHandledConservatively(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $migrationHelper = $this->get(LegacyMigrationHelper::class);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/orders_migrated.csv');

        $orderUid = (int)$migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);
        $this->assertNull(json_decode($order['legacy_order_data'], true)['order_data']);

        $outputText = $output->fetch();
        $this->assertStringContainsString('had unknown status 999', $outputText);
        $this->assertStringContainsString('country "Wonderland" not recognized', $outputText);
        $this->assertStringContainsString('unparseable orderData blob', $outputText);
        $this->assertStringContainsString('used electronic pay_mode 4', $outputText);
    }

    #[Test]
    public function lineItemWithResolvableProductIsMigratedWithCurrentPrice(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/order_items_migrated.csv');

        $this->assertStringContainsString(
            'sys_products_orders_mm_tt_products uid 2 referenced missing product uid 999, item dropped',
            $output->fetch()
        );
    }

    #[Test]
    public function lineItemWithResolvableArticleUsesArticlePrice(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/order_items_migrated.csv');
        $this->assertStringContainsString('referenced missing article uid 999, kept as product-only', $output->fetch());
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $subject = $this->subject(new BufferedOutput());
        $subject->executeUpdate();

        $this->assertFalse($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/orders_migrated.csv');
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/order_items_migrated.csv');
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchOrder(int $uid): array
    {
        return $this->fetchRow(self::LOCAL_TABLE, $uid);
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchRow(string $table, int $uid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        $row = $queryBuilder->select('*')->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid)))
            ->executeQuery()->fetchAssociative();
        return $row === false ? [] : $row;
    }
}
