<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsCategoryUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsCategoryUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products_cat';
    private const LOCAL_TABLE = 'tx_products_domain_model_category';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsCategoryUpgradeWizardTest/tt_products_cat.csv');
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesVisibleCategoriesExcludingDeleted(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/categories_migrated.csv');
        $this->assertNull($this->get(LegacyMigrationHelper::class)->resolveLocalUid(self::LEGACY_TABLE, 4, self::LOCAL_TABLE));
    }

    #[Test]
    public function parentCategoryIsReLinkedViaTheMigrationMap(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/categories_migrated.csv');
    }

    #[Test]
    public function orphanParentIsLinkedToRootAndWarns(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/categories_migrated.csv');
        $this->assertStringContainsString('referenced missing parent uid 999', $output->fetch());
    }

    #[Test]
    public function overlaysAreDeduplicatedPreferringVisibleThenHighestUid(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/categories_migrated.csv');

        $outputText = $output->fetch();
        $this->assertStringContainsString('Skipped duplicate tt_products_cat_language uid 20', $outputText);
        $this->assertStringContainsString('Skipped duplicate tt_products_cat_language uid 30', $outputText);
    }

    #[Test]
    public function overlayWithOrphanParentIsSkippedAndWarns(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/categories_migrated.csv');
        $this->assertStringContainsString('parent uid 4 was never migrated', $output->fetch());
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $subject = $this->subject(new BufferedOutput());
        $subject->executeUpdate();

        $this->assertFalse($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/categories_migrated.csv');
    }

    private function subject(BufferedOutput $output): TtProductsCategoryUpgradeWizard
    {
        $subject = $this->get(TtProductsCategoryUpgradeWizard::class);
        $subject->setOutput($output);
        return $subject;
    }
}
