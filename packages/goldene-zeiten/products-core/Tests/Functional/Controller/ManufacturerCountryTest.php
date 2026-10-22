<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The manufacturer block of a detail page names the country in the language of the page; the structured data
 * keeps the ISO code.
 */
final class ManufacturerCountryTest extends AbstractFrontendTestCase
{
    protected const LANGUAGE_PRESETS = [
        'de' => [
            'id' => 0,
            'title' => 'Deutsch',
            'locale' => 'de_DE.UTF-8',
        ],
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductManufacturerTest/products_with_manufacturer.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
            ]),
            [$this->buildDefaultLanguageConfiguration('de', '/')]
        );
    }

    #[Test]
    #[DataProvider('countryProvider')]
    public function theBlockShowsTheCountryNameInTheLanguageOfThePage(string $isoCode, string $expected): void
    {
        $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_manufacturer')
            ->update('tx_products_domain_model_manufacturer', ['country' => $isoCode], ['uid' => 1]);

        $body = $this->renderDetail(1);

        $this->assertMatchesRegularExpression('#12345 Musterstadt<br>\s*' . $expected . '\s*</address>#', $body);
        $this->assertStringContainsString('"addressCountry":"' . $isoCode . '"', $body);
    }

    public static function countryProvider(): \Generator
    {
        yield 'a known code is translated' => ['IT', 'Italien'];
        yield 'an unknown code is shown as it is' => ['XX', 'XX'];
    }

    private function renderDetail(int $productUid): string
    {
        $parameters = ['tx_productscore_productdetail[product]' => (string)$productUid];
        $hashString = '&id=2&tx_productscore_productdetail[product]=' . $productUid;
        $parameters['cHash'] = $this->get(CacheHashCalculator::class)->generateForParameters($hashString);
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/shop'))->withQueryParameters($parameters));
        $this->assertSame(200, $response->getStatusCode());
        return (string)$response->getBody();
    }
}
