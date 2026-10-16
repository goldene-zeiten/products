<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsLegacyCleanupUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Rows a migration wizard skipped (no parent, unresolvable reference) count as handled, so the cleanup
 * names them before they disappear with their table. A class of its own, because dropping tables is not
 * undone between the tests of one class on every DBMS.
 */
final class TtProductsLegacyCleanupSkippedRowsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsLegacyCleanupUpgradeWizardTest/legacy_cleanup_complete.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsLegacyCleanupSkippedRowsTest/skipped_articles.csv');
    }

    #[Test]
    public function rowsSkippedByTheMigrationAreReportedWhenTheirTableIsDropped(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsLegacyCleanupUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $this->assertStringContainsString(
            'tt_products_articles: 2 rows were skipped by the migration and are dropped without a counterpart (uids 2, 3).',
            $output->fetch()
        );
    }
}
