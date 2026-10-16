<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsArticleUpgradeWizard;
use GoldeneZeiten\Products\Core\Updates\TtProductsProductUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * tt_products' `price2` is a reseller price that `plugin.tt_products.priceNoReseller = 2` turns into the
 * charged price - for everyone when set globally, for one group when set inside a usergroup condition.
 * The wizards cannot read that TypoScript, so the integrator declares the usage in the extension
 * configuration.
 */
final class LegacyPrice2MigrationTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
    }

    #[Test]
    public function price2AsSellingPriceReplacesThePriceWhereItIsSet(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['legacyPrice2'] = 'selling';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/products_with_price2.csv');
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/Result/products_selling_price2.csv');
        $this->assertSame(0, $this->countPricePeriods());
    }

    #[Test]
    public function price2AsResellerPriceBecomesAPricePeriodOfTheResellerGroup(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['legacyPrice2'] = 'reseller';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['legacyPrice2FeGroup'] = '7';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/products_with_price2.csv');
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/Result/products_reseller_price2.csv');
    }

    #[Test]
    public function price2DeclaredUnusedKeepsThePriceAndReportsTheIgnoredValues(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['legacyPrice2'] = 'unused';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/products_with_price2.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $this->assertSame(0, $this->countPricePeriods());
        $this->assertStringContainsString('1 tt_products rows have a price2, ignored as configured', $output->fetch());
    }

    /**
     * @param array<string, string> $migrationConfiguration
     */
    #[Test]
    #[DataProvider('unusableConfigurationProvider')]
    public function productMigrationRefusesToRunWhilePrice2IsUsedWithoutAUsableConfiguration(array $migrationConfiguration): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration'] = $migrationConfiguration;
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/products_with_price2.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertFalse($subject->executeUpdate());

        $this->assertTrue($subject->updateNecessary());
        $this->assertStringContainsString('EXTENSIONS/products_core/migration/legacyPrice2', $output->fetch());
    }

    public static function unusableConfigurationProvider(): \Generator
    {
        yield 'usage not decided' => [
            'migrationConfiguration' => [
                'legacyPrice2' => 'undecided',
            ],
        ];
        yield 'reseller usage without a frontend group' => [
            'migrationConfiguration' => [
                'legacyPrice2' => 'reseller',
                'legacyPrice2FeGroup' => '0',
            ],
        ];
    }

    #[Test]
    public function articlePrice2AsSellingPriceReplacesThePriceWhereItIsSet(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['legacyPrice2'] = 'selling';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/articles_with_price2.csv');
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/Result/articles_selling_price2.csv');
    }

    #[Test]
    public function articlePrice2AsResellerPriceBecomesAPricePeriodOfTheResellerGroup(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['legacyPrice2'] = 'reseller';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['legacyPrice2FeGroup'] = '7';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/articles_with_price2.csv');
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/Result/articles_reseller_price2.csv');
    }

    #[Test]
    public function articleMigrationRefusesToRunWhilePrice2IsUsedWithoutADecision(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['legacyPrice2'] = 'undecided';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyPrice2MigrationTest/articles_with_price2.csv');
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertFalse($subject->executeUpdate());

        $this->assertTrue($subject->updateNecessary());
    }

    private function countPricePeriods(): int
    {
        return $this->get(ConnectionPool::class)
            ->getConnectionForTable('tx_products_domain_model_priceperiod')
            ->count('*', 'tx_products_domain_model_priceperiod', []);
    }
}
