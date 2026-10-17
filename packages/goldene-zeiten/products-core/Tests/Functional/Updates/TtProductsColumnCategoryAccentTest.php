<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsColumnCategoryUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Legacy shops matched their grouping columns with an accent- and case-insensitive collation, so spellings that
 * differ only in accents are one value: they become one category, and the merged spelling is reported.
 */
final class TtProductsColumnCategoryAccentTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsColumnCategoryAccentTest/accent_variants.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['columnCategories'] = 'quality:Filter';
    }

    #[Test]
    public function spellingsDifferingOnlyInAccentsBecomeOneCategory(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsColumnCategoryUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $titles = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_category')
            ->select(['title'], 'tx_products_domain_model_category', ['deleted' => 0], [], ['uid' => 'ASC'])
            ->fetchFirstColumn();
        $this->assertSame(['Filter', 'Roségold'], $titles);
        $links = $this->getConnectionPool()->getConnectionForTable('tx_products_product_category_mm')
            ->count('*', 'tx_products_product_category_mm', []);
        $this->assertSame(2, $links);
        $this->assertStringContainsString('tt_products.quality "Rosègold" was merged into the category "Roségold"', $output->fetch());
        $this->assertFalse($subject->updateNecessary());
    }
}
