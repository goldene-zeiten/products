<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsArticleUpgradeWizard;
use GoldeneZeiten\Products\Core\Updates\TtProductsProductUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A shop that made its goods to order never tracked stock in tt_products: every row carries the stock it was
 * created with. Migrated as tracked stock, each product would sell out after its first sale, so
 * `migration.unlimitedStock` migrates products and articles as not stock-limited.
 */
final class TtProductsUnlimitedStockTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/products_with_content_columns.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/articles_with_content_columns.csv');
    }

    #[Test]
    #[DataProvider('settingProvider')]
    public function productsAndArticlesAreStockLimitedAsConfigured(string $setting, int $expectedUnlimitedStock): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['unlimitedStock'] = $setting;
        foreach ([TtProductsProductUpgradeWizard::class, TtProductsArticleUpgradeWizard::class] as $wizardClass) {
            $wizard = $this->get($wizardClass);
            $wizard->setOutput(new BufferedOutput());
            $wizard->executeUpdate();
        }

        // Product 80 comes with the article fixture as migrated before; it did not pass the product wizard.
        foreach (['tx_products_domain_model_product', 'tx_products_domain_model_article'] as $table) {
            $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
            $values = $queryBuilder->select('unlimited_stock')
                ->from($table)
                ->where($queryBuilder->expr()->neq('uid', $queryBuilder->createNamedParameter($table === 'tx_products_domain_model_product' ? 80 : 0)))
                ->executeQuery()
                ->fetchFirstColumn();
            $this->assertNotSame([], $values);
            $this->assertSame([$expectedUnlimitedStock], array_values(array_unique(array_map(intval(...), $values))));
        }
    }

    public static function settingProvider(): \Generator
    {
        yield 'stock tracked by default' => [
            'setting' => '0',
            'expectedUnlimitedStock' => 0,
        ];
        yield 'stock not tracked' => [
            'setting' => '1',
            'expectedUnlimitedStock' => 1,
        ];
    }
}
