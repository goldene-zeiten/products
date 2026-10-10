<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsCategoryUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsCategoryUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products_cat';
    private const LOCAL_TABLE = 'tx_products_domain_model_category';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
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

    /**
     * Legacy category 20 points at parent 21, which comes later in uid order and is therefore not
     * mapped yet when 20 is migrated. The parent link must survive anyway.
     */
    #[Test]
    public function parentWithAHigherUidThanItsChildIsStillLinked(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsCategoryUpgradeWizardTest/parent_with_higher_uid.csv');
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $migrationHelper = $this->get(LegacyMigrationHelper::class);
        $childUid = $migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 20, self::LOCAL_TABLE);
        $parentUid = $migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 21, self::LOCAL_TABLE);
        $child = $this->getConnectionPool()->getConnectionForTable(self::LOCAL_TABLE)
            ->select(['parent_category'], self::LOCAL_TABLE, ['uid' => $childUid])
            ->fetchAssociative();
        $this->assertNotNull($parentUid);
        $this->assertSame($parentUid, (int)($child['parent_category'] ?? 0));
        $this->assertStringNotContainsString('referenced missing parent uid 21', $output->fetch());
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
