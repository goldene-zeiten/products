<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Core\Controller\Exception\CategoryPathMismatchException;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
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

    protected array $coreExtensionsToLoad = [
        'install',
        'fluid_styled_content',
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    #[Test]
    public function navigationActionRendersTheFullCategoryTreeWithNestedLinks(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryControllerTest/category_routing.csv');
        $this->writeSiteConfiguration('products', $this->buildSiteConfiguration(1), [
            $this->buildDefaultLanguageConfiguration('en', '/'),
        ]);
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products_core/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            plugin.tx_productscore.settings.pids.categoryPage = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = ProductsCore
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
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products_core/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = ProductsCore
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
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products_core/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = ProductsCore
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

    #[Test]
    public function listActionResolvesACategoryFromCategoryFieldWhenNoRouteParameterIsPresent(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/CategoryControllerTest/category_routing.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/category_list_with_category_field.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        // Renders the real tt_content row (uid 200, records=3) through the normal CType
        // dispatch, so currentContentObject carries that row's actual data - a hardcoded
        // "page.10 = USER" plugin call never populates currentContentObject and can't
        // exercise the records-based category fallback.
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products_core/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:products_core/Configuration/TypoScript/setup.typoscript',
                'EXT:products_core/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = 200
            }
        ');

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'));

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 2', $body);
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
                    'extension' => 'ProductsCore',
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
