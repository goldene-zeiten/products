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
 * tt_products keeps the product text in `note`; `description` is one of its variant columns. Every
 * legacy column carrying data the wizards do not migrate is reported, so no content is lost silently.
 */
final class LegacyContentColumnsTest extends AbstractFunctionalTestCase
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
    public function productTextAndCostColumnsAreMigrated(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/products_with_content_columns.csv');
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/Result/products_content_columns.csv');
    }

    #[Test]
    #[DataProvider('unmigratedProductColumnProvider')]
    public function productColumnsCarryingUnmigratedDataAreReported(string $expectedReport): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/products_with_content_columns.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringContainsString($expectedReport, $output->fetch());
    }

    public static function unmigratedProductColumnProvider(): \Generator
    {
        yield 'variant column' => [
            'expectedReport' => 'tt_products: 1 rows have data in "color", which is not migrated.',
        ];
        yield 'variant column sharing its name with the new text field' => [
            'expectedReport' => 'tt_products: 1 rows have data in "description", which is not migrated.',
        ];
        yield 'second text column' => [
            'expectedReport' => 'tt_products: 1 rows have data in "note2", which is not migrated.',
        ];
        yield 'tag column' => [
            'expectedReport' => 'tt_products: 1 rows have data in "quality", which is not migrated.',
        ];
    }

    #[Test]
    #[DataProvider('migratedOrEmptyProductColumnProvider')]
    public function productColumnsThatAreMigratedOrEmptyAreNotReported(string $column): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/products_with_content_columns.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringNotContainsString(sprintf('data in "%s"', $column), $output->fetch());
    }

    public static function migratedOrEmptyProductColumnProvider(): \Generator
    {
        yield 'migrated text column' => [
            'column' => 'note',
        ];
        yield 'migrated decimal column' => [
            'column' => 'directcost',
        ];
        yield 'column empty in every row' => [
            'column' => 'color2',
        ];
        yield 'system column' => [
            'column' => 'tstamp',
        ];
    }

    #[Test]
    public function theLegacyKeywordsBecomeTheProductKeywords(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/products_with_keywords.csv');
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/Result/products_keywords.csv');
    }

    #[Test]
    public function note2BecomesTheKeywordsWhenTheShopUsedItThatWay(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['note2AsKeywords'] = '1';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/products_with_content_columns.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/Result/products_note2_keywords.csv');
        $this->assertStringNotContainsString('data in "note2"', $output->fetch());
    }

    #[Test]
    public function aColumnConfiguredToBecomeCategoriesIsNotReported(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['columnCategories'] = 'quality:Filter';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/products_with_content_columns.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringNotContainsString('data in "quality"', $output->fetch());
    }

    #[Test]
    public function articleColumnsCarryingUnmigratedDataAreReported(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/articles_with_content_columns.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $this->assertStringContainsString(
            'tt_products_articles: 1 rows have data in "material", which is not migrated.',
            $output->fetch()
        );
    }
}
