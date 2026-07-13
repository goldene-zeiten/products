<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
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

    protected array $coreExtensionsToLoad = [
        'install',
        'fluid_styled_content',
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
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

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Product 1', (string)$response->getBody());
    }

    #[Test]
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

        $request = new InternalRequest('http://localhost/shop/product-1');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('Product 1', (string)$response->getBody());
    }

    #[Test]
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

        $withRelations = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop/product-1'));
        $withoutRelations = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop/product-2'));

        $this->assertStringContainsString('Product 2', (string)$withRelations->getBody());
        $this->assertStringContainsString('Product 3', (string)$withRelations->getBody());
        $this->assertStringNotContainsString('You might also like', (string)$withoutRelations->getBody());
        $this->assertStringNotContainsString('Frequently bought with', (string)$withoutRelations->getBody());
    }

    #[Test]
    public function listActionShowsAllProductsByDefault(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shop.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_list_modes.csv');
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

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 1', $body);
        $this->assertStringContainsString('Product 2', $body);
        $this->assertStringContainsString('Product 3', $body);
    }

    #[Test]
    public function listActionShowsOnlyOffersWhenModeIsOffers(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shop.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_list_modes.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        // Renders the real tt_content row (uid 101, tx_products_list_mode=offers) through
        // the normal CType dispatch, so currentContentObject carries that row's actual data -
        // a hardcoded "page.10 = USER" plugin call (as other tests in this file use) never
        // populates currentContentObject and can't exercise per-element mode configuration.
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:products/Configuration/TypoScript/setup.typoscript',
                'EXT:products/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = 101
            }
        ');

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 2', $body);
        $this->assertStringContainsString('Product 4', $body);
        $this->assertStringNotContainsString('Product 1', $body);
        $this->assertStringNotContainsString('Product 3', $body);
        $this->assertStringNotContainsString('Product 5', $body);
        $this->assertStringNotContainsString('Product 6', $body);
    }

    #[Test]
    public function listActionRespectsRecordsFieldRestriction(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shop.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_list_modes.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        // uid 106 has mode=all but records="2,4" - the records restriction must narrow an
        // otherwise-unfiltered "all" listing down to just the picked products.
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:products/Configuration/TypoScript/setup.typoscript',
                'EXT:products/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = 106
            }
        ');

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 2', $body);
        $this->assertStringContainsString('Product 4', $body);
        $this->assertStringNotContainsString('Product 1', $body);
        $this->assertStringNotContainsString('Product 3', $body);
        $this->assertStringNotContainsString('Product 5', $body);
        $this->assertStringNotContainsString('Product 6', $body);
    }

    /**
     * The restriction fixture nests category 2 under category 1 and puts product 2 in it, while
     * products 3 and 4 sit in the unrelated category 3. Product 1 is in category 1 itself.
     */
    #[Test]
    public function listActionRestrictsToTheSelectedCategoryAndItsDescendants(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shop.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_list_category_restriction.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_list_modes.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        // uid 107 has mode=all and tx_products_category="1".
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:products/Configuration/TypoScript/setup.typoscript',
                'EXT:products/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = 107
            }
        ');

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 1', $body);
        $this->assertStringContainsString('Product 2', $body);
        $this->assertStringNotContainsString('Product 3', $body);
        $this->assertStringNotContainsString('Product 4', $body);
        $this->assertStringNotContainsString('Product 5', $body);
        $this->assertStringNotContainsString('Product 6', $body);
    }

    /**
     * Product 4 is an offer too, but sits outside the selected subtree, and product 1 is inside it
     * but is no offer - so only product 2 satisfies both restrictions.
     */
    #[Test]
    public function listActionAppliesTheCategoryRestrictionOnTopOfTheSelectedMode(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/pages.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/shop.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_list_category_restriction.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/product_list_modes.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [
                $this->buildDefaultLanguageConfiguration('en', '/'),
            ]
        );
        // uid 108 has mode=offers and tx_products_category="1".
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/constants.typoscript',
                'EXT:products/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:fluid_styled_content/Configuration/TypoScript/setup.typoscript',
                'EXT:products/Configuration/TypoScript/setup.typoscript',
                'EXT:products/Tests/Functional/Fixtures/TypoScript/plugin_content_rendering.typoscript',
            ],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_products.persistence.storagePid = 2
            page = PAGE
            page.10 = CONTENT
            page.10 {
                table = tt_content
                select.where = uid = 108
            }
        ');

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $body = (string)$response->getBody();
        $this->assertStringContainsString('Product 2', $body);
        $this->assertStringNotContainsString('Product 1', $body);
        $this->assertStringNotContainsString('Product 4', $body);
    }
}
