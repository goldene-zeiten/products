<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Backend;

use GoldeneZeiten\Products\Core\Backend\ProductArchiveService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

final class ProductArchiveServiceTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductArchiveServiceTest/product_archive.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function archiveMovesOnlyOldProductsAndArticlesFromTheGivenSourcePid(): void
    {
        $subject = $this->get(ProductArchiveService::class);

        $subject->archive(sourcePid: 2, destinationPid: 3, ageDays: 1);

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/product_archive_moved.csv');
    }

    #[Test]
    public function archiveReturnsMovedRowCountsPerTable(): void
    {
        $subject = $this->get(ProductArchiveService::class);

        $counts = $subject->archive(sourcePid: 2, destinationPid: 3, ageDays: 1);

        $this->assertSame(['tx_products_domain_model_product' => 1, 'tx_products_domain_model_article' => 1], $counts);
    }

    #[Test]
    #[DataProvider('archiveNoOpProvider')]
    public function archiveIsANoOp(int $sourcePid, int $destinationPid, int $ageDays): void
    {
        $subject = $this->get(ProductArchiveService::class);

        $counts = $subject->archive(sourcePid: $sourcePid, destinationPid: $destinationPid, ageDays: $ageDays);

        $this->assertSame([], $counts);
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/product_archive_no_op.csv');
    }

    public static function archiveNoOpProvider(): \Generator
    {
        yield 'nothing is old enough' => [
            'sourcePid' => 2,
            'destinationPid' => 3,
            'ageDays' => 100_000,
        ];

        yield 'invalid destination pid' => [
            'sourcePid' => 2,
            'destinationPid' => 0,
            'ageDays' => 1,
        ];
    }
}
