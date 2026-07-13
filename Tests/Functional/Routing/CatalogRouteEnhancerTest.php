<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Routing;

use GoldeneZeiten\Products\Controller\Exception\ProductPathMismatchException;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Proves the routeEnhancers shipped by the goldene-zeiten/products Site Set are actually
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

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CatalogRouteEnhancerTest/catalog_routing.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, '/', 'Home', [
                'dependencies' => ['goldene-zeiten/products'],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => ['EXT:products/Configuration/TypoScript/constants.typoscript'],
            'setup' => ['EXT:products/Configuration/TypoScript/setup.typoscript'],
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

    private function configurePlugin(string $pluginName): void
    {
        $this->addTypoScriptToTemplateRecord(1, sprintf('
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = Products
                pluginName = %s
                vendorName = GoldeneZeiten
            }
        ', $pluginName));
    }
}
