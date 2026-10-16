<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\TtProductsPluginUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * Legacy plugin settings without an equivalent - FlexForm filters, template switches, the starting
 * point - cannot travel to the new content element, so the integrator is told which ones were dropped.
 */
final class TtProductsPluginDroppedSettingsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsPluginDroppedSettingsTest/list_with_filters.csv');
    }

    #[Test]
    #[DataProvider('droppedSettingProvider')]
    public function droppedSettingsOfAMigratedElementAreReported(string $expectedReport): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $this->assertStringContainsString($expectedReport, $output->fetch());
    }

    public static function droppedSettingProvider(): \Generator
    {
        yield 'FlexForm filter' => [
            'expectedReport' => 'product_where = "quality LIKE \'%Weißgold%\'"',
        ];
        yield 'FlexForm template switch' => [
            'expectedReport' => 'template_suffix = "RINGS"',
        ];
        yield 'starting point' => [
            'expectedReport' => 'pages = "12,13"',
        ];
        yield 'element and page' => [
            'expectedReport' => 'tt_content uid 100 on page 2 ("Shop") was migrated without these legacy settings',
        ];
    }

    #[Test]
    #[DataProvider('notReportedProvider')]
    public function settingsThatAreEmptyOrMigratedAreNotReported(string $unexpectedReport): void
    {
        $output = new BufferedOutput();
        $subject = $this->get(TtProductsPluginUpgradeWizard::class);
        $subject->setOutput($output);

        $subject->executeUpdate();

        $this->assertStringNotContainsString($unexpectedReport, $output->fetch());
    }

    public static function notReportedProvider(): \Generator
    {
        yield 'empty FlexForm field' => [
            'unexpectedReport' => 'myTS',
        ];
        yield 'migrated display mode' => [
            'unexpectedReport' => 'display_mode',
        ];
        yield 'element without dropped settings' => [
            'unexpectedReport' => 'tt_content uid 101',
        ];
    }
}
