<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Tests\Functional\Rendering;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The options of a product are chosen in its add-to-basket form, so they travel with "add to basket".
 */
final class OptionFieldsRenderingTest extends AbstractFunctionalTestCase
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
        'goldene-zeiten/products-personalization',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('pages.csv'));
        $this->importCSVDataSet(__DIR__ . '/../EventListener/Fixtures/options.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->setUpFrontendRootPage(1, [
            'constants' => ['EXT:products_core/Configuration/TypoScript/constants.typoscript'],
            'setup' => [
                'EXT:products_core/Configuration/TypoScript/setup.typoscript',
                'EXT:products_personalization/Configuration/TypoScript/setup.typoscript',
            ],
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

    #[Test]
    #[DataProvider('fieldProvider')]
    public function theAddToBasketFormCarriesTheOptionFields(string $expectedMarkup): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_productdetail[product]=1');

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))->withQueryParameters([
                'tx_productscore_productdetail[product]' => 1,
                'cHash' => $cHash,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString($expectedMarkup, (string)$response->getBody());
    }

    public static function fieldProvider(): \Generator
    {
        yield 'selection list' => [
            'expectedMarkup' => 'name="tx_productscore_basket[options][size]"',
        ];
        yield 'choice with its surcharge' => [
            'expectedMarkup' => '<option value="64">64 (oversize) (+ €15.00)</option>',
        ];
        yield 'free text field' => [
            'expectedMarkup' => 'name="tx_productscore_basket[options][engraving]"',
        ];
        yield 'free text limited to its length' => [
            'expectedMarkup' => 'maxlength="12"',
        ];
        yield 'option surcharge in the label' => [
            'expectedMarkup' => '(+&nbsp;€10.00)',
        ];
    }

    #[Test]
    public function aHiddenOptionRendersNoField(): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_productdetail[product]=1');

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))->withQueryParameters([
                'tx_productscore_productdetail[product]' => 1,
                'cHash' => $cHash,
            ])
        );

        $this->assertStringNotContainsString('[options][hidden]', (string)$response->getBody());
    }

    #[Test]
    public function aProductWithoutOptionsRendersNoFields(): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_productdetail[product]=3');

        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/shop'))->withQueryParameters([
                'tx_productscore_productdetail[product]' => 3,
                'cHash' => $cHash,
            ])
        );

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('[options]', (string)$response->getBody());
    }
}
