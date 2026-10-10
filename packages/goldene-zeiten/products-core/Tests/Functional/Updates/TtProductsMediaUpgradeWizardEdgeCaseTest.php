<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Updates;

use GoldeneZeiten\Products\Core\Updates\LegacyMigrationHelper;
use GoldeneZeiten\Products\Core\Updates\TtProductsMediaUpgradeWizard;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Media migration cases the shared fixture of {@see TtProductsMediaUpgradeWizardTest} cannot express:
 * a full batch of rows without media, a legacy file shared by two records, and a downloads-catalog
 * product whose legacy uid collides with an unrelated local product uid.
 */
final class TtProductsMediaUpgradeWizardEdgeCaseTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-legacy-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
    }

    /**
     * A full batch of legacy rows without any media precedes the one row that has some. Those rows
     * must not count as pending, or they fill every batch and hide the row behind them.
     */
    #[Test]
    public function aFullBatchOfRowsWithoutMediaDoesNotHideTheRowBehindIt(): void
    {
        $legacyConnection = $this->getConnectionPool()->getConnectionForTable('tt_products');
        $mapConnection = $this->getConnectionPool()->getConnectionForTable('tx_products_migration_map');
        for ($legacyUid = 1; $legacyUid <= LegacyMigrationHelper::BATCH_SIZE; $legacyUid++) {
            $legacyConnection->insert('tt_products', [
                'uid' => $legacyUid,
                'pid' => 1,
                'title' => 'Product without media ' . $legacyUid,
                'image' => '',
            ]);
            $mapConnection->insert('tx_products_migration_map', [
                'legacy_table' => 'tt_products',
                'legacy_uid' => $legacyUid,
                'local_table' => 'tx_products_domain_model_product',
                'local_uid' => 1000 + $legacyUid,
            ]);
        }
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsMediaUpgradeWizardEdgeCaseTest/pending_after_full_batch.csv');
        $subject = $this->get(TtProductsMediaUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $count = $this->getConnectionPool()->getConnectionForTable('sys_file_reference')->count('uid', 'sys_file_reference', [
            'tablenames' => 'tx_products_domain_model_product',
            'fieldname' => 'images',
            'uid_foreign' => 80,
            'uid_local' => 500,
        ]);
        $this->assertSame(1, $count);
    }

    /**
     * Two legacy records list the same file in uploads/pics/. The import must copy, not move, the
     * legacy file, and both records must end up referencing the one imported sys_file.
     */
    #[Test]
    public function aLegacyFileSharedByTwoRecordsIsImportedOnceAndKeptOnDisk(): void
    {
        $sourcePath = Environment::getPublicPath() . '/uploads/pics/shared.jpg';
        GeneralUtility::mkdir_deep(dirname($sourcePath));
        copy(__DIR__ . '/Fixtures/TtProductsMediaUpgradeWizardEdgeCaseTest/shared.jpg', $sourcePath);
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsMediaUpgradeWizardEdgeCaseTest/shared_raw_file.csv');
        $subject = $this->get(TtProductsMediaUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->executeUpdate());

        $this->assertFileExists($sourcePath);
        $fileUids = [];
        foreach ([80, 81] as $localUid) {
            $fileUids[$localUid] = $this->getConnectionPool()->getConnectionForTable('sys_file_reference')->select(
                ['uid_local'],
                'sys_file_reference',
                [
                    'tablenames' => 'tx_products_domain_model_product',
                    'fieldname' => 'images',
                    'uid_foreign' => $localUid,
                ]
            )->fetchOne();
        }
        $this->assertNotFalse($fileUids[80]);
        $this->assertSame((int)$fileUids[80], (int)$fileUids[81]);
        $this->assertSame(1, $this->getConnectionPool()->getConnectionForTable('sys_file')->count('uid', 'sys_file', []));
    }

    /**
     * Legacy product 5 was migrated to local product 80, while an unrelated local product 5 already
     * has a download. The catalog entry must still reach product 80, and the wizard must report
     * nothing left to do afterwards.
     */
    #[Test]
    public function catalogDownloadIsMigratedAndNoLongerPendingAfterwards(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/TtProductsMediaUpgradeWizardEdgeCaseTest/downloads_catalog.csv');
        $subject = $this->get(TtProductsMediaUpgradeWizard::class);
        $subject->setOutput(new BufferedOutput());

        $this->assertTrue($subject->updateNecessary());
        $this->assertTrue($subject->executeUpdate());
        $this->assertFalse($subject->updateNecessary());

        $count = $this->getConnectionPool()->getConnectionForTable('sys_file_reference')->count('uid', 'sys_file_reference', [
            'tablenames' => 'tx_products_domain_model_product',
            'fieldname' => 'downloads',
            'uid_foreign' => 80,
            'uid_local' => 504,
        ]);
        $this->assertSame(1, $count);
    }
}
