<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Visibility;

use GoldeneZeiten\Products\Core\DataProcessing\CategoryProductTreeProcessor;
use GoldeneZeiten\Products\EventFixture\DenyingVisibilityChecker;
use GoldeneZeiten\Products\EventFixture\ModifyProductListListener;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A product a visibility checker hides must stay hidden wherever the catalogue shows products, not only in
 * the product list and detail view.
 */
final class ProductVisibilityAcrossTheCatalogTest extends AbstractFunctionalTestCase
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
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        DenyingVisibilityChecker::$enabled = true;
        DenyingVisibilityChecker::$deniedProductUid = 2;
        ModifyProductListListener::$enabled = false;
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductVisibilityAcrossTheCatalogTest/catalog.csv');
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

    protected function tearDown(): void
    {
        DenyingVisibilityChecker::$enabled = false;
        DenyingVisibilityChecker::$deniedProductUid = 0;
        parent::tearDown();
    }

    #[Test]
    public function theCategoryListingHidesTheProduct(): void
    {
        $this->renderPlugin('CategoryList');
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=3&tx_productscore_categorylist[category]=1');

        $body = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/category-1'))->withQueryParameters([
                'tx_productscore_categorylist[category]' => 1,
                'cHash' => $cHash,
            ])
        )->getBody();

        $this->assertStringContainsString('Visible Product', $body);
        $this->assertStringNotContainsString('Gated Related Product', $body);
    }

    #[Test]
    public function theDetailViewHidesTheProductAmongTheRelatedProducts(): void
    {
        $body = $this->renderDetailOfProductOne();

        $this->assertStringContainsString('Visible Product', $body);
        $this->assertStringNotContainsString('Gated Related Product', $body);
    }

    #[Test]
    public function theDetailViewHidesTheProductAmongTheAccessories(): void
    {
        DenyingVisibilityChecker::$deniedProductUid = 3;

        $body = $this->renderDetailOfProductOne();

        $this->assertStringContainsString('Gated Related Product', $body);
        $this->assertStringNotContainsString('Gated Accessory Product', $body);
    }

    #[Test]
    public function theCategoryNavigationDoesNotCountTheProduct(): void
    {
        $this->renderPlugin('CategoryNavigation');

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();

        $this->assertStringContainsString('Category 1', $body);
        $this->assertStringContainsString('(1)', $body);
        $this->assertStringNotContainsString('(2)', $body);
    }

    #[Test]
    public function theCategoryTreeProcessorLeavesTheProductOut(): void
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

        $this->assertSame(['Visible Product'], array_column($processedData['categoryTree']['products'], 'title'));
    }

    private function renderDetailOfProductOne(): string
    {
        $this->renderPlugin('ProductDetail');
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_productdetail[product]=1');

        return (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))->withQueryParameters([
                'tx_productscore_productdetail[product]' => 1,
                'cHash' => $cHash,
            ])
        )->getBody();
    }

    private function renderPlugin(string $pluginName): void
    {
        $this->addTypoScriptToTemplateRecord(1, '
            plugin.tx_productscore.persistence.storagePid = 2
            plugin.tx_productscore.settings.pids.categoryPage = 3
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
