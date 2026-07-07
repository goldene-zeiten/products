<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Updates;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Updates\TtProductsMediaUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

final class TtProductsMediaUpgradeWizardTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-legacy-fixture',
    ];

    private TtProductsMediaUpgradeWizard $subject;
    private BufferedOutput $output;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/LegacyMigration/tt_products_media.csv');
        $this->output = new BufferedOutput();
        $this->subject = $this->get(TtProductsMediaUpgradeWizard::class);
        $this->subject->setOutput($this->output);
    }

    #[Test]
    public function updateIsNecessaryInitially(): void
    {
        self::assertTrue($this->subject->updateNecessary());
    }

    #[Test]
    public function existingFalReferencesAreCopiedToLocalRecords(): void
    {
        self::assertTrue($this->subject->executeUpdate());

        self::assertSame(500, $this->fetchReferenceFileUid('tx_products_domain_model_product', 'images', 80));
        self::assertSame(501, $this->fetchReferenceFileUid('tx_products_domain_model_product', 'downloads', 80));
        self::assertSame(502, $this->fetchReferenceFileUid('tx_products_domain_model_category', 'image', 50));
        self::assertSame(503, $this->fetchReferenceFileUid('tx_products_domain_model_article', 'images', 60));
    }

    #[Test]
    public function secondaryThumbnailsAndSliderImagesAreReportedNotMigrated(): void
    {
        $this->subject->executeUpdate();

        $output = $this->output->fetch();
        self::assertStringContainsString('tt_products uid 1: "smallimage" is a redundant thumbnail', $output);
        self::assertStringContainsString('tt_products_cat uid 1: "sliderimage" is a redundant thumbnail', $output);
    }

    #[Test]
    public function linkedDownloadsCatalogIsReportedNotMigrated(): void
    {
        $this->subject->executeUpdate();

        self::assertStringContainsString('tt_products uid 1 had catalog downloads linked', $this->output->fetch());
    }

    #[Test]
    public function missingRawFilenameFileIsWarnedAndSkipped(): void
    {
        $this->subject->executeUpdate();

        self::assertNull($this->fetchReferenceFileUid('tx_products_domain_model_product', 'images', 81));
        self::assertStringContainsString('media file "missing.jpg" not found on disk, skipped', $this->output->fetch());
    }

    #[Test]
    public function executeUpdateIsIdempotentForMigratedRows(): void
    {
        $this->subject->executeUpdate();
        $countAfterFirstRun = $this->countReferences('tx_products_domain_model_product', 'images', 80);

        self::assertTrue($this->subject->executeUpdate());

        self::assertSame($countAfterFirstRun, $this->countReferences('tx_products_domain_model_product', 'images', 80));
    }

    private function fetchReferenceFileUid(string $localTable, string $localField, int $localUid): ?int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        $fileUid = $queryBuilder->select('uid_local')->from('sys_file_reference')
            ->andWhere($queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($localTable)))
            ->andWhere($queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($localField)))
            ->andWhere($queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($localUid)))
            ->executeQuery()->fetchOne();
        return $fileUid === false ? null : (int)$fileUid;
    }

    private function countReferences(string $localTable, string $localField, int $localUid): int
    {
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('sys_file_reference');
        $queryBuilder->getRestrictions()->removeAll();
        return (int)$queryBuilder->count('uid')->from('sys_file_reference')
            ->andWhere($queryBuilder->expr()->eq('tablenames', $queryBuilder->createNamedParameter($localTable)))
            ->andWhere($queryBuilder->expr()->eq('fieldname', $queryBuilder->createNamedParameter($localField)))
            ->andWhere($queryBuilder->expr()->eq('uid_foreign', $queryBuilder->createNamedParameter($localUid)))
            ->executeQuery()->fetchOne();
    }
}
