<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Pricing;

use GoldeneZeiten\Products\Core\DataProcessing\CategoryProductTreeProcessor;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Listings show the price the basket will charge - with graduated prices and the category discount
 * applied - and not the stored base price. The category grants 10 %: 100.00 becomes 90.00, the tier
 * price 150.00 becomes 135.00 and the accessory's 30.00 becomes 27.00.
 */
final class DisplayedPriceInListingsTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/DisplayedPriceInListingsTest/catalog.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => ['EXT:products_core/Configuration/TypoScript/constants.typoscript'],
            'setup' => ['EXT:products_core/Configuration/TypoScript/setup.typoscript'],
        ]);
    }

    #[Test]
    public function theProductListShowsTheDiscountedAndTieredPrices(): void
    {
        $this->renderPlugin('ProductList');

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();

        $this->assertStringContainsString('90.00', $body);
        $this->assertStringContainsString('135.00', $body);
        $this->assertStringNotContainsString('100.00', $body);
        $this->assertStringNotContainsString('150.00', $body);
    }

    #[Test]
    public function theCategoryListingShowsTheDiscountedPrices(): void
    {
        $this->renderPlugin('CategoryList');
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=3&tx_productscore_categorylist[category]=1');

        $body = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/category-1'))->withQueryParameters([
                'tx_productscore_categorylist[category]' => 1,
                'cHash' => $cHash,
            ])
        )->getBody();

        $this->assertStringContainsString('90.00', $body);
        $this->assertStringContainsString('27.00', $body);
        $this->assertStringNotContainsString('100.00', $body);
        $this->assertStringNotContainsString('30.00', $body);
    }

    #[Test]
    public function theAccessoriesOnTheDetailPageShowTheDiscountedPrice(): void
    {
        $this->renderPlugin('ProductDetail');
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_productdetail[product]=1');

        $body = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))->withQueryParameters([
                'tx_productscore_productdetail[product]' => 1,
                'cHash' => $cHash,
            ])
        )->getBody();

        $this->assertStringContainsString('27.00', $body);
        $this->assertStringNotContainsString('30.00', $body);
    }

    #[Test]
    public function theCategoryTreeProcessorReportsTheDiscountedPrice(): void
    {
        $contentObjectRenderer = $this->get(ContentObjectRenderer::class);
        $contentObjectRenderer->setRequest(
            (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
        );

        $processedData = $this->get(CategoryProductTreeProcessor::class)->process(
            $contentObjectRenderer,
            [],
            ['entryPointCategory' => 1],
            []
        );

        $this->assertSame(
            ['90.00', '180.00', '27.00'],
            array_column($processedData['categoryTree']['products'], 'price')
        );
    }

    private function renderPlugin(string $pluginName): void
    {
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            page = PAGE
            page.10 = USER
            page.10 {
                userFunc = TYPO3\CMS\Extbase\Core\Bootstrap->run
                extensionName = ProductsCore
                pluginName = ' . $pluginName . '
                vendorName = GoldeneZeiten
            }
        ');
    }
}
