<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsLegacyCleanupUpgradeWizard;
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

    /**
     * @test
     */
    public function updateIsNecessaryWhileLegacyTablesExist(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    /**
     * @test
     */
    public function refusesToDropTablesWhileMigrationIsIncomplete(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/legacy_cleanup_incomplete.csv');

        self::assertFalse($this->subject->executeUpdate());
        self::assertTrue($this->migrationHelper->tablesExist(self::LEGACY_TABLE));
        self::assertStringContainsString('refusing to drop legacy tables', $this->output->fetch());
    }

    /**
     * Combined with the "no longer necessary" assertion rather than a separate test method: this
     * test drops the legacy tables, and MySQL/MariaDB functional runs share the schema across test
     * methods within a class (only fixture data is reset between tests, not the schema) - a second
     * test whose setUp() re-imports the same legacy-table fixture would fail with
     * "table does not exist" once this one has actually dropped it.
     *
     * @test
     */
    public function dropsLegacyTablesOnceEveryEntityIsMigratedAndIsNoLongerNecessaryAfterwards(): void
    {
        self::assertTrue($this->subject->executeUpdate());

        self::assertFalse($this->migrationHelper->tablesExist(self::LEGACY_TABLE));
        self::assertFalse($this->migrationHelper->tablesExist('tt_products'));
        self::assertFalse($this->migrationHelper->tablesExist('tt_products_articles'));
        self::assertFalse($this->migrationHelper->tablesExist('sys_products_orders'));
        self::assertStringContainsString('Dropped legacy table', $this->output->fetch());

        self::assertFalse($this->subject->updateNecessary());
    }
}
