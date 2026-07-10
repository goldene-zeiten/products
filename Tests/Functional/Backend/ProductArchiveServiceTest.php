<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Backend;

use Doctrine\DBAL\ParameterType;
use GoldeneZeiten\Products\Backend\ProductArchiveService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

final class ProductArchiveServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private ProductArchiveService $archiveService;
    private ConnectionPool $connectionPool;

    protected function setUp(): void
    {
        parent::setUp();
        $this->archiveService = $this->get(ProductArchiveService::class);
        $this->connectionPool = $this->get(ConnectionPool::class);
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/product_archive.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    #[Test]
    public function archiveMovesOnlyOldProductsAndArticlesFromTheGivenSourcePid(): void
    {
        $this->archiveService->archive(sourcePid: 2, destinationPid: 3, ageDays: 1);

        self::assertSame(3, $this->fetchPid('tx_products_domain_model_product', 100));
        self::assertSame(3, $this->fetchPid('tx_products_domain_model_article', 200));
        self::assertSame(2, $this->fetchPid('tx_products_domain_model_product', 101));
        self::assertSame(2, $this->fetchPid('tx_products_domain_model_article', 201));
        self::assertSame(4, $this->fetchPid('tx_products_domain_model_product', 102));
        self::assertSame(4, $this->fetchPid('tx_products_domain_model_article', 202));
    }

    #[Test]
    public function archiveReturnsMovedRowCountsPerTable(): void
    {
        $counts = $this->archiveService->archive(sourcePid: 2, destinationPid: 3, ageDays: 1);

        self::assertSame(['tx_products_domain_model_product' => 1, 'tx_products_domain_model_article' => 1], $counts);
    }

    #[Test]
    public function archiveReturnsEmptyArrayWhenNothingIsOldEnough(): void
    {
        $counts = $this->archiveService->archive(sourcePid: 2, destinationPid: 3, ageDays: 100_000);

        self::assertSame([], $counts);
        self::assertSame(2, $this->fetchPid('tx_products_domain_model_product', 100));
    }

    #[Test]
    public function archiveDoesNothingForAnInvalidDestinationPid(): void
    {
        $counts = $this->archiveService->archive(sourcePid: 2, destinationPid: 0, ageDays: 1);

        self::assertSame([], $counts);
        self::assertSame(2, $this->fetchPid('tx_products_domain_model_product', 100));
    }

    private function fetchPid(string $table, int $uid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable($table);
        $pid = $queryBuilder->select('pid')
            ->from($table)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($uid, ParameterType::INTEGER)))
            ->executeQuery()
            ->fetchOne();
        return (int)$pid;
    }
}
