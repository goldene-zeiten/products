<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Routing;

use GoldeneZeiten\Products\Core\Controller\Exception\ProductPathMismatchException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Proves the routeEnhancers shipped by the goldene-zeiten/products-core Site Set are actually
 * auto-loaded end-to-end: this test's own site configuration only declares a `dependencies`
 * entry, never a `routeEnhancers` key of its own - everything routing-related comes from
 * Core13/Routing/SiteSetRouteEnhancerListener.php picking up
 * Configuration/Sets/Products/route-enhancers.yaml, which is what this environment (TYPO3 13)
 * actually runs.
 */
final class CatalogRouteEnhancerTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    private const PAGE_TYPE_SUFFIX_SITE_CONFIGURATION = [
        'dependencies' => ['goldene-zeiten/products-core'],
        'routeEnhancers' => [
            'PageTypeSuffix' => [
                'type' => 'PageType',
                'default' => '.html',
                'map' => [
                    '.html' => 0,
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CatalogRouteEnhancerTest/catalog_routing.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, '/', 'Home', [
                'dependencies' => ['goldene-zeiten/products-core'],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => ['EXT:products_core/Configuration/TypoScript/constants.typoscript'],
            'setup' => ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
        ]);
    }

    #[Test]
    #[DataProvider('categoryListingPathsProvider')]
    public function categoryListingResolvesTheAutoLoadedNestedPath(string $path, string $expectedContent): void
    {
        $this->configurePlugin('CategoryList');

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $path));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($expectedContent, (string)$response->getBody());
    }

    public static function categoryListingPathsProvider(): \Generator
    {
        yield 'depth 1, category with a direct product' => [
            'path' => '/shop/main-category-1',
            'expectedContent' => 'Product B',
        ];
        yield 'depth 2, category with no direct product' => [
            'path' => '/shop/main-category-1/sub-category-5',
            'expectedContent' => 'Sub Category 5',
        ];
        yield 'depth 3, leaf category with a direct product' => [
            'path' => '/shop/main-category-1/sub-category-5/last-category-3',
            'expectedContent' => 'Product A',
        ];
        yield 'a hidden-from-slug category is skipped, shortening the visible path' => [
            'path' => '/shop/main-category-1/under-hidden',
            'expectedContent' => 'Under Hidden',
        ];
        yield 'depth 4, below the former three-level limit' => [
            'path' => '/shop/main-category-1/sub-category-5/last-category-3/deep-category-4',
            'expectedContent' => 'Product E',
        ];
    }

    #[Test]
    public function aCategoryPathTakingTheWrongBranchDoesNotResolve(): void
    {
        $this->configurePlugin('CategoryList');

        $response = $this->executeFrontendSubRequest(
            new InternalRequest('http://localhost/shop/main-category-1/sub-category-x/last-category-3')
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function aCategoryPathResolvesBehindAPageTypeSuffix(): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, '/', 'Home', self::PAGE_TYPE_SUFFIX_SITE_CONFIGURATION),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->configurePlugin('CategoryList');

        $response = $this->executeFrontendSubRequest(
            new InternalRequest('http://localhost/shop/main-category-1/sub-category-5/last-category-3/deep-category-4.html')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Product E', (string)$response->getBody());
    }

    /**
     * @param array<non-empty-string, mixed> $siteConfiguration
     */
    #[Test]
    #[DataProvider('navigationLinksProvider')]
    public function categoryNavigationLinksAreTheRoutedCategoryListUrls(
        array $siteConfiguration,
        string $navigationUrl,
        string $expectedDepth1Href,
        string $expectedDepth4Href
    ): void {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, '/', 'Home', $siteConfiguration),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->configurePlugin('CategoryNavigation');

        $response = $this->executeFrontendSubRequest(new InternalRequest($navigationUrl));

        $this->assertSame(200, $response->getStatusCode());
        $hrefs = $this->extractLinkTargetsByText((string)$response->getBody());
        $this->assertSame($expectedDepth1Href, $hrefs['Main Category 1'] ?? null);
        $this->assertSame($expectedDepth4Href, $hrefs['Deep Category 4'] ?? null);
    }

    public static function navigationLinksProvider(): \Generator
    {
        yield 'site without a page type suffix' => [
            'siteConfiguration' => [
                'dependencies' => ['goldene-zeiten/products-core'],
            ],
            'navigationUrl' => 'http://localhost/',
            'expectedDepth1Href' => '/shop/main-category-1',
            'expectedDepth4Href' => '/shop/main-category-1/sub-category-5/last-category-3/deep-category-4',
        ];
        yield 'site with a ".html" page type suffix' => [
            'siteConfiguration' => self::PAGE_TYPE_SUFFIX_SITE_CONFIGURATION,
            'navigationUrl' => 'http://localhost/',
            'expectedDepth1Href' => '/shop/main-category-1.html',
            'expectedDepth4Href' => '/shop/main-category-1/sub-category-5/last-category-3/deep-category-4.html',
        ];
    }

    #[Test]
    #[DataProvider('productAndArticlePathsProvider')]
    public function productAndArticleUrlsResolveToTheAutoLoadedProductDetailPage(string $path, string $expectedContent): void
    {
        $this->configurePlugin('ProductDetail');

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $path));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($expectedContent, (string)$response->getBody());
    }

    public static function productAndArticlePathsProvider(): \Generator
    {
        yield 'product with no category assigned at all' => [
            'path' => '/shop/product-c',
            'expectedContent' => 'Product C',
        ];
        yield 'product one category level deep, zero articles' => [
            'path' => '/shop/main-category-1/product-b',
            'expectedContent' => 'Product B',
        ];
        yield 'product nested through a hidden-from-slug category' => [
            'path' => '/shop/main-category-1/under-hidden/product-d',
            'expectedContent' => 'Product D',
        ];
        yield 'product three category levels deep' => [
            'path' => '/shop/main-category-1/sub-category-5/last-category-3/product-a',
            'expectedContent' => 'Product A',
        ];
        yield 'article replaces the product slug at the same depth (default "replace" mode)' => [
            'path' => '/shop/main-category-1/sub-category-5/last-category-3/article-a-red',
            'expectedContent' => 'Product A',
        ];
    }

    #[Test]
    #[DataProvider('mismatchedPathsProvider')]
    public function aPathNotMatchingTheProductsRealCategoryAssignmentIsRejected(string $path, int $expectedExceptionCode): void
    {
        $this->configurePlugin('ProductDetail');
        $this->expectException(ProductPathMismatchException::class);
        $this->expectExceptionCode($expectedExceptionCode);

        $this->executeFrontendSubRequest(new InternalRequest('http://localhost' . $path));
    }

    public static function mismatchedPathsProvider(): \Generator
    {
        yield 'the resolved category is real, but the product is not assigned to it' => [
            'path' => '/shop/main-category-1/product-a',
            'expectedExceptionCode' => 1783800101,
        ];
        yield 'the product is assigned to the category, but the path takes the wrong branch to it' => [
            'path' => '/shop/main-category-1/sub-category-x/last-category-3/product-a',
            'expectedExceptionCode' => 1783800102,
        ];
    }

    /**
     * @return array<string, string>
     */
    private function extractLinkTargetsByText(string $html): array
    {
        $document = new \DOMDocument();
        $document->loadHTML($html, LIBXML_NOERROR);
        $hrefs = [];
        foreach ($document->getElementsByTagName('a') as $anchor) {
            $hrefs[trim($anchor->textContent)] = $anchor->getAttribute('href');
        }
        return $hrefs;
    }

    private function configurePlugin(string $pluginName): void
    {
        $this->addTypoScriptToTemplateRecord(1, sprintf('
            plugin.tx_productscore.persistence.storagePid = 2
            plugin.tx_productscore.settings.pids.categoryPage = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = ProductsCore
                pluginName = %s
                vendorName = GoldeneZeiten
            }
        ', $pluginName));
    }
}
