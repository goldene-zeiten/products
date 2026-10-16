<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsPluginUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A legacy list filtered with a simple product_where on a column that became categories lists those
 * categories; anything more complex stays reported.
 */
final class TtProductsPluginProductWhereTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginProductWhereTest/product_where.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['columnCategories'] = 'quality:Filter';
    }

    #[Test]
    #[DataProvider('mappableFilterProvider')]
    public function aFilterOnACategoryColumnBecomesACategorySelection(int $contentUid, string $expectedCategories): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $this->assertSame($expectedCategories, $this->categorySelectionOf($contentUid));
        $this->assertStringNotContainsString(sprintf('tt_content uid %d on page', $contentUid), $output->fetch());
    }

    public static function mappableFilterProvider(): \Generator
    {
        yield 'LIKE with wildcards' => [
            'contentUid' => 100,
            'expectedCategories' => '2',
        ];
        yield 'alternatives in a different case' => [
            'contentUid' => 101,
            'expectedCategories' => '2,3',
        ];
    }

    #[Test]
    #[DataProvider('unmappableFilterProvider')]
    public function aFilterThatIsNoCategorySelectionStaysReported(int $contentUid): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertSame('', $this->categorySelectionOf($contentUid));
        $this->assertStringContainsString(sprintf('tt_content uid %d on page 2 ("Shop") was migrated without these legacy settings: product_where', $contentUid), $output->fetch());
    }

    public static function unmappableFilterProvider(): \Generator
    {
        yield 'column without categories' => [
            'contentUid' => 102,
        ];
        yield 'combined conditions' => [
            'contentUid' => 103,
        ];
        yield 'value without a category' => [
            'contentUid' => 104,
        ];
    }

    #[Test]
    public function thePluginMigrationWaitsForTheColumnCategories(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsColumnCategoryTest/column_values.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertFalse($subject->executeUpdate());

        $this->assertStringContainsString('products_ttProductsColumnCategories', $output->fetch());
    }

    private function categorySelectionOf(int $contentUid): string
    {
        $row = $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['tx_products_category'], 'tt_content', ['uid' => $contentUid])
            ->fetchAssociative();
        return (string)($row['tx_products_category'] ?? '');
    }
}
