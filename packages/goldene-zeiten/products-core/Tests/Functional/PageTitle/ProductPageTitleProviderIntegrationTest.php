<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\PageTitle;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class ProductPageTitleProviderIntegrationTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    #[Test]
    public function productDetailPageUsesTheProductTitleInThePageTitleTag(): void
    {
        $this->bootstrapProductDetailFrontend();

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop/gadget'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesRegularExpression('#<title>[^<]*Gadget[^<]*</title>#', (string)$response->getBody());
    }

    #[Test]
    public function aPageWithoutACurrentProductFallsBackToTheDefaultRecordTitle(): void
    {
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => ['EXT:products_core/Configuration/TypoScript/constants.typoscript'],
            'setup' => ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
        ]);
        $this->addTypoScriptToTemplateRecord(1, 'page = PAGE');

        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/'));

        $this->assertSame(200, $response->getStatusCode());
        $this->assertMatchesRegularExpression('#<title>[^<]*Root[^<]*</title>#', (string)$response->getBody());
    }

    private function bootstrapProductDetailFrontend(): void
    {
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductPageTitleProviderIntegrationTest/product_page_title.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, '/', 'Home', [
                'routeEnhancers' => [
                    'ProductsDetail' => [
                        'type' => 'Extbase',
                        'extension' => 'ProductsCore',
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
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => ['EXT:products_core/Configuration/TypoScript/constants.typoscript'],
            'setup' => ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
        ]);
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = ProductsCore
                pluginName = ProductDetail
                vendorName = GoldeneZeiten
            }
        ');
    }
}
