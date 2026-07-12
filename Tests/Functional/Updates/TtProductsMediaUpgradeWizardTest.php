<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\TtProductsMediaUpgradeWizard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsMediaUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsMediaUpgradeWizardTest/tt_products_media.csv');
    }

    private function subject(BufferedOutput $output): TtProductsMediaUpgradeWizard
    {
        $subject = $this->get(TtProductsMediaUpgradeWizard::class);
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
    public function existingFalReferencesAreCopiedToLocalRecords(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/media_references_migrated.csv');
    }

    /**
     * @param string[] $expectedOutputStrings
     */
    #[Test]
    #[DataProvider('outOfScopeMediaWarningProvider')]
    public function outOfScopeMediaIsReportedNotMigrated(array $expectedOutputStrings): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $outputText = $output->fetch();
        foreach ($expectedOutputStrings as $expectedOutputString) {
            $this->assertStringContainsString($expectedOutputString, $outputText);
        }
    }

    /**
     * @return \Generator<string, array{expectedOutputStrings: string[]}>
     */
    public static function outOfScopeMediaWarningProvider(): \Generator
    {
        yield 'secondary thumbnails and slider images' => [
            'expectedOutputStrings' => [
                'tt_products uid 1: "smallimage" is a redundant thumbnail',
                'tt_products_cat uid 1: "sliderimage" is a redundant thumbnail',
            ],
        ];
        yield 'linked downloads catalog' => [
            'expectedOutputStrings' => [
                'tt_products uid 1 had catalog downloads linked',
            ],
        ];
    }

    #[Test]
    public function missingRawFilenameFileIsWarnedAndSkipped(): void
    {
        $output = new BufferedOutput();
        $subject = $this->subject($output);

        $subject->executeUpdate();

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/media_references_migrated.csv');
        $this->assertStringContainsString('media file "missing.jpg" not found on disk, skipped', $output->fetch());
    }

    #[Test]
    public function executeUpdateIsIdempotentForMigratedRows(): void
    {
        $subject = $this->subject(new BufferedOutput());
        $subject->executeUpdate();

        $this->assertTrue($subject->executeUpdate());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/media_references_migrated.csv');
    }
}
