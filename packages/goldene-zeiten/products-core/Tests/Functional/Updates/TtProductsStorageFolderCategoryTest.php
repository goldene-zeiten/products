<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsStorageFolderCategoryUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A tt_products shop without tt_products_cat grouped its catalog by storage folders. With
 * `migration.storageFolderCategories` that grouping becomes a category tree.
 */
final class TtProductsStorageFolderCategoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsStorageFolderCategoryTest/folder_catalog.csv');
    }

    #[Test]
    public function theStorageFoldersBecomeACategoryTreeTheProductsAreAssignedTo(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['storageFolderCategories'] = '1';
        $subject = $this->get(TtProductsStorageFolderCategoryUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsStorageFolderCategoryTest/Result/folder_categories.csv');
    }

    #[Test]
    public function runningTheWizardAgainCreatesNothingTwice(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['storageFolderCategories'] = '1';
        $subject = $this->get(TtProductsStorageFolderCategoryUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsStorageFolderCategoryTest/Result/folder_categories.csv');
    }

    #[Test]
    public function withoutTheOptionTheWizardHasNothingToDo(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_core']['migration']['storageFolderCategories'] = '0';
        $subject = $this->get(TtProductsStorageFolderCategoryUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertFalse($subject->updateNecessary());
    }
}
