<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsProductUpgradeWizard;
use GoldeneZeiten\Products\EventFixture\CollectMigratedLegacyColumnsListener;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * A column another extension migrates is not data the core migration loses.
 */
final class LegacyColumnsMigratedElsewhereTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function tearDown(): void
    {
        CollectMigratedLegacyColumnsListener::$columns = [];
        parent::tearDown();
    }

    #[Test]
    public function aColumnAnotherExtensionMigratesIsNotReported(): void
    {
        CollectMigratedLegacyColumnsListener::$columns = ['color'];
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/LegacyContentColumnsTest/products_with_content_columns.csv');
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsProductUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $report = $output->fetch();
        $this->assertStringNotContainsString('data in "color"', $report);
        $this->assertStringContainsString('data in "quality"', $report);
    }
}
