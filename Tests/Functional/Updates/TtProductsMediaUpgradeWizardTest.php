<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Core\Updates\TtProductsMediaUpgradeWizard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsMediaUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
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
     * The fixture links legacy product uid 1 (-> local product uid 80) to a downloads-catalog
     * entry (legacy uid 999) via tt_products_products_mm_downloads, with an existing
     * sys_file_reference on tt_products_downloads.file_uid pointing at sys_file uid 504.
     */
    #[Test]
    public function linkedDownloadsCatalogEntryIsMigratedToProductDownloads(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_reference');
        $migratedReference = $connection->select(
            ['*'],
            'sys_file_reference',
            [
                'tablenames' => 'tx_products_domain_model_product',
                'fieldname' => 'downloads',
                'uid_foreign' => 80,
                'uid_local' => 504,
            ]
        )->fetchAssociative();

        $this->assertNotFalse($migratedReference, 'Expected the downloads-catalog file to be migrated to product 80.');
    }

    /**
     * Legacy product uid 2 (-> local product uid 81) has two catalog downloads and no datasheet, so
     * the first migrated reference lands on sorting position 0. The second must be appended after it
     * rather than reusing 0, or the two files would share a sorting position.
     */
    #[Test]
    public function eachCatalogDownloadOfTheSameProductGetsItsOwnSortingPosition(): void
    {
        $subject = $this->subject(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_reference');
        $sortings = $connection->select(
            ['sorting_foreign'],
            'sys_file_reference',
            [
                'tablenames' => 'tx_products_domain_model_product',
                'fieldname' => 'downloads',
                'uid_foreign' => 81,
            ],
            [],
            ['sorting_foreign' => 'ASC']
        )->fetchFirstColumn();

        $this->assertSame([0, 1], array_map('intval', $sortings));
    }

    #[Test]
    public function reRunningTheWizardDoesNotDuplicateTheMigratedDownloadsCatalogReference(): void
    {
        $subject = $this->subject(new BufferedOutput());
        $subject->executeUpdate();

        $this->assertTrue($subject->executeUpdate());

        $connection = $this->getConnectionPool()->getConnectionForTable('sys_file_reference');
        $count = (int)$connection->count('uid', 'sys_file_reference', [
            'tablenames' => 'tx_products_domain_model_product',
            'fieldname' => 'downloads',
            'uid_foreign' => 80,
            'uid_local' => 504,
        ]);

        $this->assertSame(1, $count);
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
