<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Updates\TtProductsProductUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsProductUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products';
    private const LOCAL_TABLE = 'tx_products_domain_model_product';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsProductUpgradeWizardTest/tt_products.csv');
    }

    private function subject(BufferedOutput $output): TtProductsProductUpgradeWizard
    {
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);
        return $subject;
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesVisibleProductsExcludingDeleted(): void
    {
        $subject = $this->subject(new BufferedOutput());
        $migrationHelper = $this->get(LegacyMigrationHelper::class);

        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/products_migrated.csv');
        $this->assertNull($migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 4, self::LOCAL_TABLE));
    }

    #[Test]
    public function priceIsFormattedAsADecimalString(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/products_migrated.csv');
    }

    #[Test]
    public function taxCategoryIsMappedToTheResolvedTaxClass(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/products_migrated.csv');
        $this->assertStringContainsString('unknown taxcat_id 9', $output->fetch());
    }

    #[Test]
    public function categoryIsLinkedViaTheMigrationMap(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/product_category_mm.csv');
    }

    #[Test]
    public function orphanCategoryIsLeftUnassignedAndWarns(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/product_category_mm.csv');
        $this->assertStringContainsString('referenced missing category uid 999', $output->fetch());
    }

    #[Test]
    public function presentImageTriggersAnOutOfScopeNotice(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertStringContainsString('tt_products uid 2 had an image', $output->fetch());
    }

    #[Test]
    public function overlaysAreDeduplicatedAndOrphansAreSkipped(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/products_migrated.csv');

        $outputText = $output->fetch();
        $this->assertStringContainsString('Skipped duplicate tt_products_language uid 20', $outputText);
        $this->assertStringContainsString('parent uid 999 was never migrated', $outputText);
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $subject = $this->subject(new BufferedOutput());
        $subject->executeUpdate();

        $this->assertFalse($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/products_migrated.csv');
    }

}
