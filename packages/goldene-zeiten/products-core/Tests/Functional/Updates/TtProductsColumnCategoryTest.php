<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsColumnCategoryUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Legacy columns used for grouping - a manufacturer in `form`, tags in `quality` - become category trees
 * as configured in `migration.columnCategories`.
 */
final class TtProductsColumnCategoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsColumnCategoryTest/column_values.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['columnCategories'] = 'form:Hersteller, quality:Filter';
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['columnCategoriesMultiValue'] = 'quality';
    }

    #[Test]
    public function eachConfiguredColumnBecomesACategoryTreeTheProductsAreAssignedTo(): void
    {
        $subject = $this->get(TtProductsColumnCategoryUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsColumnCategoryTest/Result/column_categories.csv');
    }

    #[Test]
    public function runningTheWizardAgainCreatesNothingTwice(): void
    {
        $subject = $this->get(TtProductsColumnCategoryUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsColumnCategoryTest/Result/column_categories.csv');
    }

    #[Test]
    public function withoutConfiguredColumnsTheWizardHasNothingToDo(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['columnCategories'] = '';
        $subject = $this->get(TtProductsColumnCategoryUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function aConfiguredColumnTheLegacyTableLacksIsReported(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['columnCategories'] = 'brand:Marke';
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsColumnCategoryUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $this->assertStringContainsString('tt_products has no column "brand"', $output->fetch());
    }
}
