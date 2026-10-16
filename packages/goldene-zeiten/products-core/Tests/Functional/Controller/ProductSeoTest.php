<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The product detail page tells search engines and social networks what it shows: title, description,
 * keywords, indexing, Open Graph and structured product data. An article may override the product's texts.
 */
final class ProductSeoTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductSeoTest/products_with_seo.csv');
    }

    #[Test]
    #[DataProvider('productHeadProvider')]
    public function theDetailPageHeadDescribesTheProduct(int $productUid, int $articleUid, string $expectedMarkup): void
    {
        $this->assertStringContainsString($expectedMarkup, $this->renderDetail($productUid, $articleUid));
    }

    public static function productHeadProvider(): \Generator
    {
        yield 'SEO title' => [
            'productUid' => 1,
            'articleUid' => 0,
            'expectedMarkup' => 'Engagement ring in white gold</title>',
        ];
        yield 'meta description' => [
            'productUid' => 1,
            'articleUid' => 0,
            'expectedMarkup' => '<meta name="description" content="A white gold engagement ring with a brilliant.">',
        ];
        yield 'keywords' => [
            'productUid' => 1,
            'articleUid' => 0,
            'expectedMarkup' => '<meta name="keywords" content="ring, white gold">',
        ];
        yield 'no index' => [
            'productUid' => 1,
            'articleUid' => 0,
            'expectedMarkup' => '<meta name="robots" content="noindex,follow">',
        ];
        yield 'Open Graph title' => [
            'productUid' => 1,
            'articleUid' => 0,
            'expectedMarkup' => '<meta property="og:title" content="The ring for the question">',
        ];
        yield 'Open Graph description' => [
            'productUid' => 1,
            'articleUid' => 0,
            'expectedMarkup' => '<meta property="og:description" content="Say yes.">',
        ];
        yield 'description falls back to the cropped product text' => [
            'productUid' => 2,
            'articleUid' => 0,
            'expectedMarkup' => '<meta name="description" content="A plain ring for every day. It is made of solid silver and polished by hand in our workshop, so every piece is a little different from the next one…">',
        ];
        yield 'Open Graph title falls back to the title' => [
            'productUid' => 2,
            'articleUid' => 0,
            'expectedMarkup' => '<meta property="og:title" content="Plain ring">',
        ];
        yield 'article overrides the description' => [
            'productUid' => 3,
            'articleUid' => 3,
            'expectedMarkup' => '<meta name="description" content="The engagement ring in 750 gold.">',
        ];
        yield 'article inherits the product keywords' => [
            'productUid' => 3,
            'articleUid' => 3,
            'expectedMarkup' => '<meta name="keywords" content="ring, wedding">',
        ];
    }

    #[Test]
    public function aProductThatMayBeIndexedCarriesNoRobotsTag(): void
    {
        $this->assertStringNotContainsString('name="robots"', $this->renderDetail(2, 0));
    }

    #[Test]
    public function theDetailPageCarriesStructuredProductData(): void
    {
        $body = $this->renderDetail(1, 0);

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $match));
        $data = json_decode($match[1] ?? '', true);
        $this->assertSame('Product', $data['@type']);
        $this->assertSame('Engagement ring', $data['name']);
        $this->assertSame('R1', $data['sku']);
        $this->assertSame('Offer', $data['offers']['@type']);
        $this->assertSame('EUR', $data['offers']['priceCurrency']);
    }

    private function renderDetail(int $productUid, int $articleUid): string
    {
        $parameters = ['tx_productscore_productdetail[product]' => (string)$productUid];
        if ($articleUid > 0) {
            $parameters['tx_productscore_productdetail[selectedArticle]'] = (string)$articleUid;
        }
        $hashString = '&id=2';
        foreach ($parameters as $name => $value) {
            $hashString .= '&' . $name . '=' . $value;
        }
        $parameters['cHash'] = $this->get(CacheHashCalculator::class)->generateForParameters($hashString);
        $response = $this->executeFrontendSubRequest((new InternalRequest('http://localhost/shop'))->withQueryParameters($parameters));
        $this->assertSame(200, $response->getStatusCode());
        return (string)$response->getBody();
    }
}
