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

    private TtProductsLegacyCleanupUpgradeWizard $subject;
    private BufferedOutput $output;
    private LegacyMigrationHelper $migrationHelper;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/legacy_cleanup_complete.csv');
        $this->migrationHelper = $this->get(LegacyMigrationHelper::class);
        $this->output = new BufferedOutput();
        $this->subject = $this->get(TtProductsLegacyCleanupUpgradeWizard::class);
        $this->subject->setOutput($this->output);
    }

    #[Test]
    public function updateIsNecessaryWhileLegacyTablesExist(): void
    {
        $this->assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function refusesToDropTablesWhileMigrationIsIncomplete(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/legacy_cleanup_incomplete.csv');

        $this->assertFalse($this->subject->executeUpdate());
        $this->assertTrue($this->migrationHelper->tablesExist(self::LEGACY_TABLE));
        $this->assertStringContainsString('refusing to drop legacy tables', $this->output->fetch());
    }

    #[Test]
    public function dropsLegacyTablesOnceEveryEntityIsMigratedAndIsNoLongerNecessaryAfterwards(): void
    {
        $this->assertTrue($this->subject->executeUpdate());

        $this->assertFalse($this->migrationHelper->tablesExist(self::LEGACY_TABLE));
        $this->assertFalse($this->migrationHelper->tablesExist('tt_products'));
        $this->assertFalse($this->migrationHelper->tablesExist('tt_products_articles'));
        $this->assertFalse($this->migrationHelper->tablesExist('sys_products_orders'));
        $this->assertStringContainsString('Dropped legacy table', $this->output->fetch());

        $this->assertFalse($this->subject->updateNecessary());
    }
}
