<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsArticleUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsArticleUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    private const LEGACY_TABLE = 'tt_products_articles';
    private const LOCAL_TABLE = 'tx_products_domain_model_article';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsArticleUpgradeWizardTest/tt_products_articles.csv');
    }

    private function subject(BufferedOutput $output): TtProductsArticleUpgradeWizard
    {
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput($output);
        return $subject;
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $this->assertTrue($subject->updateNecessary());
    }

    #[Test]
    public function executeUpdateMigratesArticleWithResolvedProduct(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/articles_migrated.csv');
    }

    #[Test]
    public function isAddedPriceFlexFormFlagMapsToSurchargePriceMode(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/articles_migrated.csv');
    }

    #[Test]
    public function articleWithMissingProductIsSkippedAndWarns(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $migrationHelper = $this->get(LegacyMigrationHelper::class);
        $this->assertTrue($subject->executeUpdate());

        $this->assertSame(0, $migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 2, self::LOCAL_TABLE));
        $this->assertStringContainsString('referenced missing product uid 999', $output->fetch());
    }

    #[Test]
    public function deletedArticleIsNeverMigrated(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $migrationHelper = $this->get(LegacyMigrationHelper::class);
        $subject->executeUpdate();

        $this->assertNull($migrationHelper->resolveLocalUid(self::LEGACY_TABLE, 3, self::LOCAL_TABLE));
    }

    #[Test]
    public function overlaysAreDeduplicatedAndOrphansAreSkipped(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/articles_migrated.csv');

        $outputText = $output->fetch();
        $this->assertStringContainsString('Skipped duplicate tt_products_articles_language uid 10', $outputText);
        $this->assertStringContainsString('parent uid 999 was never migrated', $outputText);
    }

    #[Test]
    public function executeUpdateIsIdempotent(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);
        $subject->executeUpdate();

        $this->assertFalse($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/articles_migrated.csv');
    }
}
