<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Event;

use GoldeneZeiten\Products\EventFixture\ModifyProductListListener;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class ProductListEventDispatchTest extends AbstractFunctionalTestCase
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
        ModifyProductListListener::$enabled = false;
        ModifyProductListListener::$invocationCount = 0;
    }

    #[Test]
    public function productListEventIsDispatchedAndMutationTakesEffect(): void
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

        ModifyProductListListener::$enabled = true;
        ModifyProductListListener::$invocationCount = 0;

        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertGreaterThanOrEqual(1, ModifyProductListListener::$invocationCount);
        $this->assertStringNotContainsString('Product 1', (string)$response->getBody());
    }
}
