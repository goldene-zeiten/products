<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Visibility;

use GoldeneZeiten\Products\EventFixture\DenyingVisibilityChecker;
use GoldeneZeiten\Products\EventFixture\ModifyProductListListener;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Error\Http\PageNotFoundException;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Frontend functional tests for product visibility filtering.
 * Tests integration with list and detail views through the HTTP stack.
 */
final class ProductVisibilityFrontendTest extends AbstractFunctionalTestCase
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
        'goldene-zeiten/products',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        DenyingVisibilityChecker::$enabled = false;
        DenyingVisibilityChecker::$deniedProductUid = 0;
        ModifyProductListListener::$enabled = false;
    }

    #[Test]
    public function deniedProductIsHiddenFromTheList(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shop.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:products/Configuration/TypoScript/setup.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = Products
                pluginName = ProductList
                vendorName = GoldeneZeiten
            }
        ');
        DenyingVisibilityChecker::$enabled = true;
        DenyingVisibilityChecker::$deniedProductUid = 1;

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $body = (string)$response->getBody();
        $this->assertStringNotContainsString('Product 1', $body);
        $this->assertStringContainsString('Product 2', $body);
    }

    #[Test]
    public function deniedProductDetailReturns404(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shop.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, '/', 'Home', [
                'routeEnhancers' => [
                    'ProductsDetail' => [
                        'type' => 'Extbase',
                        'extension' => 'Products',
                        'plugin' => 'ProductDetail',
                        'routes' => [
                            [
                                'routePath' => '/{product}',
                                '_controller' => 'Product::show',
                                '_arguments' => [
                                    'product' => 'product',
                                ],
                            ],
                        ],
                        'aspects' => [
                            'product' => [
                                'type' => 'PersistedAliasMapper',
                                'tableName' => 'tx_products_domain_model_product',
                                'routeFieldName' => 'slug',
                            ],
                        ],
                    ],
                ],
            ]),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:products/Configuration/TypoScript/setup.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = Products
                pluginName = ProductDetail
                vendorName = GoldeneZeiten
            }
        ');
        DenyingVisibilityChecker::$enabled = true;
        DenyingVisibilityChecker::$deniedProductUid = 1;

        $request = new InternalRequest('http://localhost/shop/product-1');
        $this->expectException(PageNotFoundException::class);
        $this->executeFrontendSubRequest($request);
    }
}
