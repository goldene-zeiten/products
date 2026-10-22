<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A product's manufacturer reaches the structured data and the GPSR block of the detail page.
 */
final class ProductManufacturerTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductManufacturerTest/products_with_manufacturer.csv');
    }

    #[Test]
    public function structuredDataCarriesBrandAndManufacturer(): void
    {
        $data = $this->structuredData($this->renderDetail(1));

        $this->assertSame(['@type' => 'Brand', 'name' => 'Stelmach'], $data['brand']);
        $this->assertSame('Organization', $data['manufacturer']['@type']);
        $this->assertSame('Stelmach Trauringe GmbH', $data['manufacturer']['name']);
        $this->assertSame('https://stelmach.example.com', $data['manufacturer']['url']);
        $this->assertSame('info@stelmach.example.com', $data['manufacturer']['email']);
        $this->assertSame('PostalAddress', $data['manufacturer']['address']['@type']);
        $this->assertSame('Hauptstrasse 1', $data['manufacturer']['address']['streetAddress']);
        $this->assertSame('12345', $data['manufacturer']['address']['postalCode']);
        $this->assertSame('Musterstadt', $data['manufacturer']['address']['addressLocality']);
        $this->assertSame('DE', $data['manufacturer']['address']['addressCountry']);
    }

    #[Test]
    public function structuredDataOfAProductWithoutManufacturerHasNoBrand(): void
    {
        $data = $this->structuredData($this->renderDetail(2));

        $this->assertArrayNotHasKey('brand', $data);
        $this->assertArrayNotHasKey('manufacturer', $data);
    }

    #[Test]
    public function theDetailPageShowsTheManufacturerInformation(): void
    {
        $body = $this->renderDetail(1);

        $this->assertStringContainsString('Manufacturer information', $body);
        $this->assertStringContainsString('Stelmach Trauringe GmbH', $body);
        $this->assertStringContainsString('Hauptstrasse 1', $body);
        $this->assertStringContainsString('12345 Musterstadt', $body);
        $this->assertStringContainsString('EU Rep Ltd', $body);
        $this->assertStringContainsString('Rue de la Loi 16, 1000 Brussels', $body);
    }

    #[Test]
    public function theDetailPageOfAProductWithoutManufacturerShowsNoManufacturerBlock(): void
    {
        $this->assertStringNotContainsString('Manufacturer information', $this->renderDetail(2));
    }

    /**
     * @return array<string, mixed>
     */
    private function structuredData(string $body): array
    {
        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $match));
        return json_decode($match[1] ?? '', true);
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
