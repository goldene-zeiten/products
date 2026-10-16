<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsLegacyCleanupUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A class of its own: dropping tables is not undone between the tests of one class on every DBMS.
 */
final class TtProductsLegacyCleanupArticleRelationTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsLegacyCleanupUpgradeWizardTest/legacy_cleanup_complete.csv');
    }

    #[Test]
    public function dropsTheArticleRelationTableTheArticleMigrationReads(): void
    {
        $subject = $this->get(TtProductsLegacyCleanupUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $this->assertFalse($this->get(LegacyMigrationHelper::class)->tablesExist('tt_products_products_mm_articles'));
    }
}
