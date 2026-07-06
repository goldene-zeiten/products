<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsOrderUpgradeWizard;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class TtProductsOrderUpgradeWizardTest extends FunctionalTestCase
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

    /**
     * @test
     */
    public function updateIsNecessaryInitially(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function deletedOrderIsNeverMigrated(): void
    {
        $this->subject->executeUpdate();

        self::assertNull($this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 3, self::LOCAL_TABLE));
    }

    /**
     * @test
     */
    public function normalOrderIsMigratedWithResolvedStatusAndCountry(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);

        self::assertSame('TRACK1', $order['order_number']);
        self::assertSame('invoice', $order['payment_method']);
        self::assertSame('paid', $order['payment_status']);
        self::assertSame('confirmed', $order['status']);
        self::assertSame('INV-001', $order['invoice_number']);
        self::assertSame(9999, (int)$order['total_gross']);
        self::assertSame('DE', $order['tax_country']);
        self::assertSame('', $order['legacy_country_name']);
        self::assertNotSame(0, (int)$order['terms_accepted_at']);
    }

    /**
     * @test
     */
    public function billingAddressIsCreatedAndLinked(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);
        $address = $this->fetchAddress((int)$order['billing_address']);

        self::assertSame('billing', $address['address_type']);
        self::assertSame('Jane', $address['first_name']);
        self::assertSame('Doe', $address['last_name']);
        self::assertSame('Mrs.', $address['salutation']);
        self::assertSame('Main St', $address['street']);
        self::assertSame('1', $address['house_number']);
        self::assertSame('DE', $address['country']);
    }

    /**
     * @test
     */
    public function parsedOrderDataIsStoredAsJson(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);
        $legacyOrderData = json_decode($order['legacy_order_data'], true);

        self::assertSame('1.2.3.4', $legacyOrderData['client_ip']);
        self::assertSame(['version' => '1.0'], $legacyOrderData['order_data']);
    }

    /**
     * @test
     */
    public function unknownStatusUnrecognizedCountryAndUnparseableBlobAreHandledConservatively(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE);
        $order = $this->fetchOrder($orderUid);

        self::assertSame('LEGACY-2', $order['order_number']);
        self::assertSame('new', $order['status']);
        self::assertSame('open', $order['payment_status']);
        self::assertSame('', $order['tax_country']);
        self::assertSame('Wonderland', $order['legacy_country_name']);
        self::assertNull(json_decode($order['legacy_order_data'], true)['order_data']);

        $output = $this->output->fetch();
        self::assertStringContainsString('had unknown status 999', $output);
        self::assertStringContainsString('country "Wonderland" not recognized', $output);
        self::assertStringContainsString('unparseable orderData blob', $output);
        self::assertStringContainsString('used electronic pay_mode 4', $output);
    }

    /**
     * @test
     */
    public function lineItemWithResolvableProductIsMigratedWithCurrentPrice(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 1, self::LOCAL_TABLE);
        $items = $this->fetchItems($orderUid);

        self::assertCount(1, $items);
        self::assertSame('Existing Product', $items[0]['title']);
        self::assertSame(2, (int)$items[0]['quantity']);
        self::assertSame(1000, (int)$items[0]['unit_price_gross']);
        self::assertSame(2000, (int)$items[0]['line_total_gross']);

        self::assertStringContainsString(
            'sys_products_orders_mm_tt_products uid 2 referenced missing product uid 999, item dropped',
            $this->output->fetch()
        );
    }

    /**
     * @test
     */
    public function lineItemWithResolvableArticleUsesArticlePrice(): void
    {
        $this->subject->executeUpdate();

        $orderUid = (int)$this->migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE);
        $items = $this->fetchItems($orderUid);
        $itemsByItemNumber = array_column($items, null, 'item_number');

        self::assertCount(2, $items);
        self::assertSame(1250, (int)$itemsByItemNumber['ART1']['unit_price_gross']);
        self::assertSame(1000, (int)$itemsByItemNumber['PROD1']['unit_price_gross']);
        self::assertSame(0, (int)$itemsByItemNumber['PROD1']['article']);
        self::assertStringContainsString('referenced missing article uid 999, kept as product-only', $this->output->fetch());
    }

    /**
     * @test
     */
    public function executeUpdateIsIdempotent(): void
    {
        $this->subject->executeUpdate();
        $ordersAfterFirstRun = $this->countRows(self::LOCAL_TABLE);
        $itemsAfterFirstRun = $this->countRows(self::LOCAL_ITEM_TABLE);

        self::assertFalse($this->subject->updateNecessary());
        self::assertTrue($this->subject->executeUpdate());
        self::assertSame($ordersAfterFirstRun, $this->countRows(self::LOCAL_TABLE));
        self::assertSame($itemsAfterFirstRun, $this->countRows(self::LOCAL_ITEM_TABLE));
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
