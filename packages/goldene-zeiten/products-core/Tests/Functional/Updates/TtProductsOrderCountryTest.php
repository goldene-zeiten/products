<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsOrderUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * tt_products stores the country of an order as a name in the shop's language ("Deutschland"), and the ISO
 * 3166 alpha-3 code of the billing address in its order data. The code is what identifies the country.
 */
final class TtProductsOrderCountryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsOrderCountryTest/orders.csv');
    }

    #[Test]
    public function theBillingCountryCodeOfTheOrderDataNamesTheCountry(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $order = $this->orderRow(10);
        $this->assertSame('DE', $order['tax_country']);
        $this->assertSame('', $order['legacy_country_name']);
        $this->assertStringNotContainsString('"Deutschland" not recognized', $output->fetch());
    }

    #[Test]
    public function aNameWithoutCodeThatNoCountryCarriesIsKept(): void
    {
        $subject = $this->get(TtProductsOrderUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();

        $order = $this->orderRow(11);
        $this->assertSame('', $order['tax_country']);
        $this->assertSame('Republik Kugelland', $order['legacy_country_name']);
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
