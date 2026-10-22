<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsManufacturerUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * The legacy manufacturer column named in `migration.manufacturerColumn` becomes manufacturer records the
 * migrated products point to; `tt_products.address` and `tt_products.www` complete them.
 */
final class TtProductsManufacturerTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsManufacturerTest/manufacturer_values.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['manufacturerColumn'] = 'form';
    }

    #[Test]
    public function everyDistinctValueBecomesAManufacturerTheProductsPointTo(): void
    {
        $subject = $this->get(TtProductsManufacturerUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $manufacturers = $this->manufacturers();
        $this->assertSame(['Stelmach', 'Stelmach Trauringe'], array_column($manufacturers, 'name'));
        $products = $this->productManufacturers();
        $this->assertSame($manufacturers[0]['uid'], $products[80]);
        $this->assertSame($manufacturers[1]['uid'], $products[81]);
        $this->assertSame($manufacturers[0]['uid'], $products[82]);
        $this->assertSame(0, $products[83]);
    }

    #[Test]
    public function theAddressOfTheLegacyAddressRecordFillsTheManufacturer(): void
    {
        $subject = $this->get(TtProductsManufacturerUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();

        $stelmach = $this->manufacturers()[0];
        $this->assertSame('Hauptstrasse 1', $stelmach['street']);
        $this->assertSame('12345', $stelmach['zip']);
        $this->assertSame('Musterstadt', $stelmach['city']);
        $this->assertSame('DE', $stelmach['country']);
        $this->assertSame('info@stelmach.example', $stelmach['email']);
        $this->assertSame('https://address.example', $stelmach['website']);
    }

    #[Test]
    public function theProductWebsiteFillsAnEmptyManufacturerWebsiteOnly(): void
    {
        $subject = $this->get(TtProductsManufacturerUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();

        $manufacturers = $this->manufacturers();
        $this->assertSame('https://address.example', $manufacturers[0]['website']);
        $this->assertSame('https://trauringe.example', $manufacturers[1]['website']);
    }

    #[Test]
    public function namesThatLookLikeDuplicatesAreReportedNotMerged(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsManufacturerUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringContainsString('"Stelmach" / "Stelmach Trauringe"', $output->fetch());
        $this->assertCount(2, $this->manufacturers());
    }

    #[Test]
    public function runningTheWizardAgainChangesNothing(): void
    {
        $subject = $this->get(TtProductsManufacturerUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());
        $subject->executeUpdate();
        $before = [$this->manufacturers(), $this->productManufacturers()];

        $this->assertTrue($subject->executeUpdate());

        $this->assertSame($before, [$this->manufacturers(), $this->productManufacturers()]);
    }

    #[Test]
    public function withoutAConfiguredColumnTheWizardHasNothingToDo(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['manufacturerColumn'] = '';
        $subject = $this->get(TtProductsManufacturerUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function aManufacturerAProductAlreadyHasIsNotOverwritten(): void
    {
        $this->get(ConnectionPool::class)->getConnectionForTable('tx_products_domain_model_product')
            ->update('tx_products_domain_model_product', ['manufacturer' => 999], ['uid' => 80]);
        $subject = $this->get(TtProductsManufacturerUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();

        $this->assertSame(999, $this->productManufacturers()[80]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function manufacturers(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_manufacturer');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('*')->from('tx_products_domain_model_manufacturer')->orderBy('uid')->executeQuery();
        $manufacturers = [];
        while ($row = $result->fetchAssociative()) {
            $row['uid'] = (int)$row['uid'];
            $row['pid'] = (int)$row['pid'];
            $manufacturers[] = $row;
        }
        return $manufacturers;
    }

    /**
     * @return array<int, int> product uid => manufacturer uid
     */
    private function productManufacturers(): array
    {
        $queryBuilder = $this->get(ConnectionPool::class)->getQueryBuilderForTable('tx_products_domain_model_product');
        $queryBuilder->getRestrictions()->removeAll();
        $result = $queryBuilder->select('uid', 'manufacturer')->from('tx_products_domain_model_product')->executeQuery();
        $products = [];
        while ($row = $result->fetchAssociative()) {
            $products[(int)$row['uid']] = (int)$row['manufacturer'];
        }
        return $products;
    }
}
