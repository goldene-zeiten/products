<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsLegacyCleanupUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsLegacyCleanupUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products_cat';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsLegacyCleanupUpgradeWizardTest/legacy_cleanup_complete.csv');
    }

    private function subject(BufferedOutput $output): TtProductsLegacyCleanupUpgradeWizard
    {
        $subject = $this->get(TtProductsLegacyCleanupUpgradeWizard::class);
        $subject->setOutput($output);
        return $subject;
    }

    #[Test]
    public function updateIsNecessaryWhileLegacyTablesExist(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $this->assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function refusesToDropTablesWhileMigrationIsIncomplete(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $migrationHelper = $this->get(LegacyMigrationHelper::class);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsLegacyCleanupUpgradeWizardTest/legacy_cleanup_incomplete.csv');

        $this->assertFalse($subject->executeUpdate());
        $this->assertTrue($migrationHelper->tablesExist(self::LEGACY_TABLE));
        $this->assertStringContainsString('refusing to drop legacy tables', $output->fetch());
    }

    #[Test]
    public function dropsLegacyTablesOnceEveryEntityIsMigratedAndIsNoLongerNecessaryAfterwards(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $migrationHelper = $this->get(LegacyMigrationHelper::class);
        $this->assertTrue($subject->executeUpdate());

        $this->assertFalse($migrationHelper->tablesExist(self::LEGACY_TABLE));
        $this->assertFalse($migrationHelper->tablesExist('tt_products'));
        $this->assertFalse($migrationHelper->tablesExist('tt_products_articles'));
        $this->assertFalse($migrationHelper->tablesExist('sys_products_orders'));
        $this->assertFalse($migrationHelper->tablesExist('sys_products_visited_products'));
        $this->assertFalse($migrationHelper->tablesExist('sys_products_fe_users_mm_visited_products'));
        $this->assertStringContainsString('Dropped legacy table', $output->fetch());

        $this->assertFalse($subject->updateNecessary());
    }
}
