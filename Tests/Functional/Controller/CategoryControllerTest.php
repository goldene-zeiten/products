<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Controller\Exception\CategoryPathMismatchException;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class CategoryControllerTest extends AbstractFunctionalTestCase
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

    #[Test]
    public function navigationActionRendersTheFullCategoryTreeWithNestedLinks(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryControllerTest/category_routing.csv');
        $this->writeSiteConfiguration('products', $this->buildSiteConfiguration(1), [
            $this->buildDefaultLanguageConfiguration('en', '/'),
        ]);
        $this->setUpFrontendRootPage(1, ['setup' => ['EXT:products/Configuration/TypoScript/setup.typoscript']]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            plugin.tx_products.settings.pids.categoryPage = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = Products
                pluginName = CategoryNavigation
                vendorName = GoldeneZeiten
            }
        ');

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'));

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Main Category 1', $body);
        $this->assertStringContainsString('Last Category 3', $body);
        $this->assertStringContainsString('/shop/main-category-1/sub-category-5/last-category-3', $body);
    }

    #[Test]
    public function theFullNestedSlugPathResolvesToTheLeafCategorysProducts(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryControllerTest/category_routing.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, '/', 'Home', $this->categoryRouteEnhancers()),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, ['setup' => ['EXT:products/Configuration/TypoScript/setup.typoscript']]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = Products
                pluginName = CategoryList
                vendorName = GoldeneZeiten
            }
        ');

        $response = $this->executeFrontendSubRequest(
            new InternalRequest('http://localhost/shop/main-category-1/sub-category-5/last-category-3')
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Product 2', (string)$response->getBody());
    }

    #[Test]
    public function aPathCombiningSegmentsFromDifferentBranchesIsRejected(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryControllerTest/category_routing.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, '/', 'Home', $this->categoryRouteEnhancers()),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, ['setup' => ['EXT:products/Configuration/TypoScript/setup.typoscript']]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = Products
                pluginName = CategoryList
                vendorName = GoldeneZeiten
            }
        ');
        $this->expectException(CategoryPathMismatchException::class);
        $this->expectExceptionCode(1783800000);

        $this->executeFrontendSubRequest(
            new InternalRequest('http://localhost/shop/main-category-1/sub-category-x/last-category-3')
        );
    }

    /**
     * @return array<non-empty-string, mixed>
     */
    private function categoryRouteEnhancers(): array
    {
        $aspect = static fn(): array => [
            'type' => 'PersistedAliasMapper',
            'tableName' => 'tx_products_domain_model_category',
            'routeFieldName' => 'slug',
        ];
        return [
            'routeEnhancers' => [
                'ProductsCategoryList' => [
                    'type' => 'Extbase',
                    'extension' => 'Products',
                    'plugin' => 'CategoryList',
                    'routes' => [
                        [
                            'routePath' => '/{category1}',
                            '_controller' => 'Category::list',
                            '_arguments' => ['category1' => 'category'],
                        ],
                        [
                            'routePath' => '/{category1}/{category2}',
                            '_controller' => 'Category::list',
                            '_arguments' => ['category2' => 'category'],
                        ],
                        [
                            'routePath' => '/{category1}/{category2}/{category3}',
                            '_controller' => 'Category::list',
                            '_arguments' => ['category3' => 'category'],
                        ],
                    ],
                    'aspects' => [
                        'category1' => $aspect(),
                        'category2' => $aspect(),
                        'category3' => $aspect(),
                    ],
                ],
            ],
        ];
    }
}
