<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsPluginUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * tt_products listed the products of its folders that also matched product_where. A migrated list keeps the
 * folders as its categories and the product_where categories as filter categories every product must carry.
 */
final class TtProductsPluginFolderAndFilterTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginFolderAndFilterTest/folder_and_filter.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['columnCategories'] = 'quality:Filter';
    }

    #[Test]
    #[DataProvider('listProvider')]
    public function folderCategoriesAndFilterCategoriesAreKeptApart(int $contentUid, string $categories, string $filterCategories): void
    {
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $row = $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['tx_products_category', 'tx_products_filter_categories'], 'tt_content', ['uid' => $contentUid])
            ->fetchAssociative();
        $this->assertIsArray($row);
        $this->assertSame($categories, (string)$row['tx_products_category']);
        $this->assertSame($filterCategories, (string)$row['tx_products_filter_categories']);
    }

    public static function listProvider(): \Generator
    {
        yield 'folder with product_where' => [
            'contentUid' => 100,
            'categories' => '4',
            'filterCategories' => '2',
        ];
        yield 'folder alone' => [
            'contentUid' => 101,
            'categories' => '4',
            'filterCategories' => '',
        ];
    }
}
