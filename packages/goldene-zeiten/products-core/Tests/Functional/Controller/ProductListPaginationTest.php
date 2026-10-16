<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use Doctrine\DBAL\Platforms\SQLitePlatform;
use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Each product list element decides how many products it shows, in which order, and whether the rest is
 * reachable through page links.
 */
final class ProductListPaginationTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductListPaginationTest/paged_lists.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'pids' => [
                            'storageFolder' => 2,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    /**
     * @param string[] $expectedProducts
     * @param string[] $unexpectedProducts
     */
    #[Test]
    #[DataProvider('listPageProvider')]
    public function aListShowsItsConfiguredSliceOfTheCatalog(int $contentUid, int $page, array $expectedProducts, array $unexpectedProducts): void
    {
        $platform = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_product')->getDatabasePlatform();
        if ($contentUid === 200 && $platform instanceof SQLitePlatform) {
            $this->markTestSkipped('TYPO3 stores decimal columns as strings on SQLite, so a price order is a text order there.');
        }
        $body = $this->renderList($contentUid, $page);

        foreach ($expectedProducts as $product) {
            $this->assertStringContainsString('>' . $product . '<', $body);
        }
        foreach ($unexpectedProducts as $product) {
            $this->assertStringNotContainsString('>' . $product . '<', $body);
        }
    }

    public static function listPageProvider(): \Generator
    {
        yield 'first page, most expensive first' => [
            'contentUid' => 200,
            'page' => 1,
            'expectedProducts' => ['Product 1', 'Product 5'],
            'unexpectedProducts' => ['Product 2', 'Product 6', 'Product 3'],
        ];
        yield 'second page continues the order' => [
            'contentUid' => 200,
            'page' => 2,
            'expectedProducts' => ['Product 2', 'Product 6'],
            'unexpectedProducts' => ['Product 1', 'Product 5', 'Product 4'],
        ];
        yield 'first page by title' => [
            'contentUid' => 203,
            'page' => 1,
            'expectedProducts' => ['Product 1', 'Product 2'],
            'unexpectedProducts' => ['Product 3', 'Product 4'],
        ];
        yield 'second page by title' => [
            'contentUid' => 203,
            'page' => 2,
            'expectedProducts' => ['Product 3', 'Product 4'],
            'unexpectedProducts' => ['Product 1', 'Product 2', 'Product 5'],
        ];
        yield 'limited list without pagination' => [
            'contentUid' => 201,
            'page' => 1,
            'expectedProducts' => ['Product 1', 'Product 2', 'Product 3'],
            'unexpectedProducts' => ['Product 4', 'Product 5'],
        ];
        yield 'no limit shows the whole catalog' => [
            'contentUid' => 202,
            'page' => 1,
            'expectedProducts' => ['Product 1', 'Product 3', 'Product 6'],
            'unexpectedProducts' => [],
        ];
    }

    #[Test]
    public function aPagedListLinksItsPages(): void
    {
        $body = $this->renderList(203, 1);

        $this->assertStringContainsString('class="pagination', $body);
        $this->assertStringContainsString('href="/shop/page/3"', $body);
    }

    #[Test]
    public function aPageLinkResolvesToThatPage(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update('tt_content', ['hidden' => 1], ['pid' => 2]);
        $connection->update('tt_content', ['hidden' => 0], ['uid' => 203]);

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop/page/2'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('>Product 3<', (string)$response->getBody());
        $this->assertStringNotContainsString('>Product 1<', (string)$response->getBody());
    }

    #[Test]
    public function aCategoryListPagesTheProductsOfItsCategory(): void
    {
        $firstPage = $this->renderList(210, 1);
        $secondPage = $this->renderList(210, 2, 'tx_productscore_categorylist');

        $this->assertStringContainsString('>Product 1<', $firstPage);
        $this->assertStringContainsString('>Product 2<', $firstPage);
        $this->assertStringNotContainsString('>Product 3<', $firstPage);
        $this->assertStringContainsString('class="pagination', $firstPage);
        $this->assertStringContainsString('>Product 3<', $secondPage);
        $this->assertStringNotContainsString('>Product 1<', $secondPage);
    }

    #[Test]
    public function aListWithoutPaginationRendersNoPageLinks(): void
    {
        $this->assertStringNotContainsString('class="pagination', $this->renderList(201, 1));
    }

    private function renderList(int $contentUid, int $page, string $pluginNamespace = 'tx_productscore_productlist'): string
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tt_content');
        $connection->update('tt_content', ['hidden' => 1], ['pid' => 2]);
        $connection->update('tt_content', ['hidden' => 0], ['uid' => $contentUid]);
        $queryParameters = [];
        if ($page > 1) {
            $queryParameters = [
                $pluginNamespace . '[currentPage]' => (string)$page,
                'cHash' => $this->get(CacheHashCalculator::class)->generateForParameters(
                    '&id=2&' . $pluginNamespace . '[currentPage]=' . $page
                ),
            ];
        }
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/shop'))->withQueryParameters($queryParameters));
        $this->assertSame(200, $response->getStatusCode());
        return (string)$response->getBody();
    }
}
