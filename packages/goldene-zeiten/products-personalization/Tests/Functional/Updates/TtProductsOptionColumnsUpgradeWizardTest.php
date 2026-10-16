<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyColumnReporter;
use GoldeneZeiten\Products\Personalization\Updates\TtProductsOptionColumnsUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * tt_products offered choices such as ring sizes or engraving fonts as selection-list columns (`48;50;52`);
 * the columns named in `migration.optionColumns` become options of the migrated products. The engraving
 * text itself had no column, so a free text option can be added next to such a list.
 */
final class TtProductsOptionColumnsUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
        'goldene-zeiten/products-personalization',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/legacy_option_columns.csv');
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_personalization']['migration'] = [
            'optionColumns' => 'color:Ring size, color2:Bracelet length, size:Engraving font',
            'personalizingOptionColumns' => 'size',
            'optionNoneValues' => 'keine, none',
            'optionTextColumns' => 'size:Engraving text',
        ];
    }

    #[Test]
    public function eachConfiguredColumnBecomesAnOptionOfTheProductsUsingIt(): void
    {
        $subject = $this->get(TtProductsOptionColumnsUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/options.csv');
    }

    #[Test]
    public function runningTheWizardAgainCreatesNothingTwice(): void
    {
        $subject = $this->get(TtProductsOptionColumnsUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $subject->executeUpdate();
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/options.csv');
    }

    #[Test]
    public function withoutConfiguredColumnsTheWizardHasNothingToDo(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_personalization']['migration'] = [];
        $subject = $this->get(TtProductsOptionColumnsUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertFalse($subject->updateNecessary());
    }

    #[Test]
    public function aConfiguredColumnTheLegacyTableLacksIsReported(): void
    {
        $GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['products_personalization']['migration']['optionColumns'] = 'color3:Surface';
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsOptionColumnsUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->executeUpdate());

        $this->assertStringContainsString('tt_products has no column "color3"; it is left out.', $output->fetch());
    }

    #[Test]
    public function theProductMigrationNoLongerReportsTheConfiguredColumnsAsLost(): void
    {
        $output = new BufferedOutput();

        $this->get(LegacyColumnReporter::class)->report('tt_products', [], $output);

        $report = $output->fetch();
        $this->assertStringNotContainsString('data in "color"', $report);
        $this->assertStringNotContainsString('data in "size"', $report);
    }
}
