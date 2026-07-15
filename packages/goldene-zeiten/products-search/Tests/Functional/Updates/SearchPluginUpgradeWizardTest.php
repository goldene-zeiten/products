<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Search\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsPluginUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * The core plugin wizard migrates a legacy SEARCH plugin into this extension's content element and
 * its tt_content fields. Those fields only exist where this extension is installed, which is why
 * these cases live here rather than beside the wizard's other cases in the core package.
 */
final class SearchPluginUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-search',
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
    public function searchPluginWithFirstLetterMode(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SearchPluginUpgradeWizardTest/search_plugin_firstletter.csv');
        $subject = $this->subject($output);

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/SearchPluginUpgradeWizardTest/Result/search_plugin_firstletter_migrated.csv');
    }

    #[Test]
    public function searchPluginWithKeyFieldMode(): void
    {
        $output = new BufferedOutput();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/SearchPluginUpgradeWizardTest/search_plugin_keyfield.csv');
        $subject = $this->subject($output);

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/SearchPluginUpgradeWizardTest/Result/search_plugin_keyfield_migrated.csv');
    }
}
