<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsPluginUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A legacy list restricted by its starting point lists the categories its storage folders became.
 */
final class TtProductsPluginStartingPointTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginStartingPointTest/starting_points.csv');
    }

    #[Test]
    public function aStartingPointWhoseFolderBecameACategorySelectsThatCategory(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $this->assertSame('3', $this->categorySelectionOf(100));
        $this->assertStringNotContainsString('tt_content uid 100 on page', $output->fetch());
    }

    #[Test]
    public function aStartingPointWithoutCategoryIsStillReported(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertSame('3', $this->categorySelectionOf(101));
        $this->assertStringContainsString('pages = "99"', $output->fetch());
    }

    #[Test]
    public function thePluginMigrationWaitsForTheFolderCategoriesWhenTheyAreEnabled(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['storageFolderCategories'] = '1';
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsStorageFolderCategoryTest/folder_catalog.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertFalse($subject->executeUpdate());

        $this->assertSame('', $this->categorySelectionOf(100));
        $this->assertStringContainsString('products_ttProductsStorageFolderCategories', $output->fetch());
    }

    private function categorySelectionOf(int $contentUid): string
    {
        $row = $this->getConnectionPool()->getConnectionForTable('tt_content')
            ->select(['tx_products_category'], 'tt_content', ['uid' => $contentUid])
            ->fetchAssociative();
        return (string)($row['tx_products_category'] ?? '');
    }
}
