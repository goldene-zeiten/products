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
    private const LOCAL_ADDRESS_TABLE = 'tx_products_domain_model_orderaddress';
    private const LOCAL_ITEM_TABLE = 'tx_products_domain_model_orderitem';

    private TtProductsOrderUpgradeWizard $subject;
    private BufferedOutput $output;
    private LegacyMigrationHelper $migrationHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/sys_products_orders.csv');
        $this->migrationHelper = $this->get(LegacyMigrationHelper::class);
        $this->output = new BufferedOutput();
        $this->subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $this->subject->setOutput($this->output);
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        $this->assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function deletedOrderIsNeverMigrated(): void
    {
        $this->subject->executeUpdate();

        $this->assertNull($this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 3, self::LOCAL_TABLE));
    }

    #[Test]
    public function normalOrderIsMigratedWithResolvedStatusAndCountry(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);

        $this->assertSame('TRACK1', $order['order_number']);
        $this->assertSame('invoice', $order['payment_method']);
        $this->assertSame('paid', $order['payment_status']);
        $this->assertSame('confirmed', $order['status']);
        $this->assertSame('INV-001', $order['invoice_number']);
        $this->assertSame(9999, (int)$order['total_gross']);
        $this->assertSame('DE', $order['tax_country']);
        $this->assertSame('', $order['legacy_country_name']);
        $this->assertNotSame(0, (int)$order['terms_accepted_at']);
    }

    #[Test]
    public function billingAddressIsCreatedAndLinked(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);
        $address = $this->fetchAddress((int)$order['billing_address']);

        $this->assertSame('billing', $address['address_type']);
        $this->assertSame('Jane', $address['first_name']);
        $this->assertSame('Doe', $address['last_name']);
        $this->assertSame('Mrs.', $address['salutation']);
        $this->assertSame('Main St', $address['street']);
        $this->assertSame('1', $address['house_number']);
        $this->assertSame('DE', $address['country']);
    }

    #[Test]
    public function parsedOrderDataIsStoredAsJson(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);
        $legacyOrderData = json_decode($order['legacy_order_data'], true);

        $this->assertSame('1.2.3.4', $legacyOrderData['client_ip']);
        $this->assertSame(['version' => '1.0'], $legacyOrderData['order_data']);
    }

    #[Test]
    public function unknownStatusUnrecognizedCountryAndUnparseableBlobAreHandledConservatively(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);

        $this->assertSame('LEGACY-2', $order['order_number']);
        $this->assertSame('new', $order['status']);
        $this->assertSame('open', $order['payment_status']);
        $this->assertSame('', $order['tax_country']);
        $this->assertSame('Wonderland', $order['legacy_country_name']);
        $this->assertNull(json_decode($order['legacy_order_data'], true)['order_data']);

        $output = $this->output->fetch();
        $this->assertStringContainsString('had unknown status 999', $output);
        $this->assertStringContainsString('country "Wonderland" not recognized', $output);
        $this->assertStringContainsString('unparseable orderData blob', $output);
        $this->assertStringContainsString('used electronic pay_mode 4', $output);
    }

    #[Test]
    public function lineItemWithResolvableProductIsMigratedWithCurrentPrice(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $items = $this->fetchItems($orderUid);

        $this->assertCount(1, $items);
        $this->assertSame('Existing Product', $items[0]['title']);
        $this->assertSame(2, (int)$items[0]['quantity']);
        $this->assertSame(1000, (int)$items[0]['unit_price_gross']);
        $this->assertSame(2000, (int)$items[0]['line_total_gross']);

        $this->assertStringContainsString(
            'sys_products_orders_mm_tt_products uid 2 referenced missing product uid 999, item dropped',
            $this->output->fetch()
        );
    }

    #[Test]
    public function lineItemWithResolvableArticleUsesArticlePrice(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE);
        $items = $this->fetchItems($orderUid);
        $itemsByItemNumber = array_column($items, null, 'item_number');

        $this->assertCount(2, $items);
        $this->assertSame(1250, (int)$itemsByItemNumber['ART1']['unit_price_gross']);
        $this->assertSame(1000, (int)$itemsByItemNumber['PROD1']['unit_price_gross']);
        $this->assertSame(0, (int)$itemsByItemNumber['PROD1']['article']);
        $this->assertStringContainsString('referenced missing article uid 999, kept as product-only', $this->output->fetch());
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $this->subject->executeUpdate();
        $ordersAfterFirstRun = $this->countRows(self::LOCAL_TABLE);
        $itemsAfterFirstRun = $this->countRows(self::LOCAL_ITEM_TABLE);

        $this->assertFalse($this->subject->updateNecessary());
        $this->assertTrue($this->subject->executeUpdate());
        $this->assertSame($ordersAfterFirstRun, $this->countRows(self::LOCAL_TABLE));
        $this->assertSame($itemsAfterFirstRun, $this->countRows(self::LOCAL_ITEM_TABLE));
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
    private function fetchAddress(int $uid): array
    {
        return $this->fetchRow(self::LOCAL_ADDRESS_TABLE, $uid);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchItems(int $orderUid): array
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable(self::LOCAL_ITEM_TABLE);
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder->select('*')->from(self::LOCAL_ITEM_TABLE)
            ->where($queryBuilder->expr()->eq('parent_order', $queryBuilder->createNamedParameter($orderUid)))
            ->executeQuery()->fetchAllAssociative();
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

    private function countRows(string $table): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')->from($table)->executeQuery()->fetchOne();
    }
}
