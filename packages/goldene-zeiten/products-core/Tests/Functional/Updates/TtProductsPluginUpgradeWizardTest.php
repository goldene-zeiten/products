<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsPluginUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsPluginUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
    }

    private function subject(BufferedOutput $output): TtProductsPluginUpgradeWizard
    {
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);
        return $subject;
    }

    #[Test]
    public function singleModeConvertsListTypeToProductList(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/single_mode.csv');
        $subject = $this->subject($output);

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/Result/single_mode_migrated.csv');
    }

    #[Test]
    public function multiModeSplitsElementIntoMultipleRows(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/multi_mode.csv');
        $subject = $this->subject($output);

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/Result/multi_mode_migrated.csv');
    }

    #[Test]
    public function checkoutModesDeduplicateIntoSingleElement(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/checkout_modes.csv');
        $subject = $this->subject($output);

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/Result/checkout_modes_migrated.csv');
    }

    #[Test]
    public function selectionsRemappedWithMigrationMap(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/selections_remapped.csv');
        $subject = $this->subject($output);

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/Result/selections_remapped_migrated.csv');
    }

    #[Test]
    public function unsupportedModeLeaveElementUntouched(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/unsupported_mode.csv');
        $subject = $this->subject($output);

        $this->assertFalse($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/Result/unsupported_mode_unchanged.csv');
        $outputStr = $output->fetch();
        $this->assertStringContainsString('uid 100', $outputStr);
        $this->assertStringContainsString('LISTGIFTS', $outputStr);
    }

    #[Test]
    public function terminationAndIdempotency(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/termination_idempotency.csv');
        $subject = $this->subject($output);

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        // Count rows before second run
        $rowsAfterFirst = $this->countRows('tt_content');

        // Run again - should be idempotent
        $output2 = new BufferedOutput();
        $subject2 = $this->subject($output2);
        $this->assertTrue($subject2->executeUpdate());

        // Count rows after second run - should be same
        $rowsAfterSecond = $this->countRows('tt_content');
        $this->assertSame($rowsAfterFirst, $rowsAfterSecond);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/Result/termination_idempotency_migrated.csv');
    }

    #[Test]
    public function prerequisiteGateBlocksExecutionWithUnmigratedProducts(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginUpgradeWizardTest/prerequisite_gate.csv');
        $subject = $this->subject($output);

        $this->assertFalse($subject->executeUpdate());
        $outputStr = $output->fetch();
        $this->assertStringContainsString('products_ttProductsProductMigration', $outputStr);
    }

    private function countRows(string $table): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable($table);
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('*')
            ->from($table)
            ->executeQuery()
            ->fetchOne();
    }
}
