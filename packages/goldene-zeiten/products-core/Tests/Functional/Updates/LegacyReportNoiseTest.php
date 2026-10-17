<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsArticleUpgradeWizard;
use GoldeneZeiten\Products\Core\Updates\TtProductsPluginUpgradeWizard;
use GoldeneZeiten\Products\Core\Updates\TtProductsProductUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The migration reports lose their value when default values count as data: an unrestricted access group
 * "0", a unit factor of 1, a FlexForm option left at "0". Such values are no content the migration loses.
 */
final class LegacyReportNoiseTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyReportNoiseTest/legacy_rows.csv');
    }

    #[Test]
    public function neutralProductValuesAreNoUnmigratedData(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $report = $output->fetch();
        $this->assertStringNotContainsString('data in "fe_group"', $report);
        $this->assertStringNotContainsString('data in "unit_factor"', $report);
    }

    #[Test]
    public function theArticleSlugIsMigratedAndANeutralAccessGroupIsNotReported(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsArticleUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $report = $output->fetch();
        $this->assertStringNotContainsString('data in "slug"', $report);
        $this->assertStringNotContainsString('data in "fe_group"', $report);
        $slug = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_article')
            ->select(['slug'], 'tx_products_domain_model_article', ['title' => 'Gold 585'])
            ->fetchOne();
        $this->assertSame('/ring/gold-585', $slug);
    }

    #[Test]
    public function aFlexFormOptionLeftAtZeroIsNoDroppedSetting(): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringNotContainsString('param_mode', $output->fetch());
    }
}
