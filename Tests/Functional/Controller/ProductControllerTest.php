<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class ProductControllerTest extends AbstractFunctionalTestCase
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

    /**
     * @test
     */
    public function listActionWorks(): void
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

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Product 1', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function showActionWorksWithSlug(): void
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

        $request = new InternalRequest('http://localhost/shop/product-1');
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Product 1', (string)$response->getBody());
    }

    /**
     * @test
     */
    public function showActionRendersRelatedAndAccessoryProducts(): void
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

        $withRelations = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop/product-1'));
        $withoutRelations = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop/product-2'));

        self::assertStringContainsString('Product 2', (string)$withRelations->getBody());
        self::assertStringContainsString('Product 3', (string)$withRelations->getBody());
        self::assertStringNotContainsString('You might also like', (string)$withoutRelations->getBody());
        self::assertStringNotContainsString('Frequently bought with', (string)$withoutRelations->getBody());
    }
}
