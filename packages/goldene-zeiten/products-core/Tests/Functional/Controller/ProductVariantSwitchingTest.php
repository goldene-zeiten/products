<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class ProductVariantSwitchingTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductVariantSwitchingTest/product_with_variants.csv');
    }

    /**
     * @param int[] $attributeValueUids
     * @param string[] $unexpectedStrings
     */
    #[Test]
    #[DataProvider('variantSelectionProvider')]
    public function variantSelectionAffectsThePriceAndStockDisplay(
        array $attributeValueUids,
        string $expectedString,
        array $unexpectedStrings,
        ?string $expectedOutOfStockRegex = null,
    ): void {
        $response = $this->executeFrontendSubRequest($this->requestFor($attributeValueUids));

        $body = (string)$response->getBody();
        $this->assertStringContainsString($expectedString, $body);
        foreach ($unexpectedStrings as $unexpectedString) {
            $this->assertStringNotContainsString($unexpectedString, $body);
        }
        if ($expectedOutOfStockRegex !== null) {
            $this->assertMatchesRegularExpression($expectedOutOfStockRegex, $body);
        }
    }

    public static function variantSelectionProvider(): \Generator
    {
        yield 'no variant selected shows the prompt' => [
            'attributeValueUids' => [],
            'expectedString' => 'Please choose a variant',
            'unexpectedStrings' => [],
        ];

        yield 'selecting the small variant shows its own price and stock' => [
            'attributeValueUids' => [1],
            'expectedString' => '15.00',
            'unexpectedStrings' => ['20.00'],
        ];

        yield 'selecting the large out-of-stock variant shows its own price and out-of-stock state' => [
            'attributeValueUids' => [2],
            'expectedString' => '20.00',
            'unexpectedStrings' => ['15.00'],
            'expectedOutOfStockRegex' => '/out.of.stock|Out of Stock/i',
        ];
    }

    #[Test]
    public function withoutAChosenVariantTheLowestVariantPriceIsShownAsFromPrice(): void
    {
        $body = (string)$this->executeFrontendSubRequest($this->requestFor())->getBody();

        $this->assertMatchesRegularExpression('#<span class="price price-from">\s*From\s+[^<\d]*15\.00#', $body);
        $this->assertDoesNotMatchRegularExpression('#<span class="price[^"]*">[^<]*20\.00#', $body);
    }

    #[Test]
    public function withoutAChosenVariantTheStructuredDataOffersThePriceRangeOfTheVariants(): void
    {
        $body = (string)$this->executeFrontendSubRequest($this->requestFor())->getBody();

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $match));
        $offers = json_decode($match[1] ?? '', true)['offers'] ?? [];
        $this->assertSame('AggregateOffer', $offers['@type'] ?? null);
        $this->assertSame('15.00', $offers['lowPrice'] ?? null);
        $this->assertSame('20.00', $offers['highPrice'] ?? null);
        $this->assertSame(2, $offers['offerCount'] ?? null);
        $this->assertSame('https://schema.org/InStock', $offers['availability'] ?? null);
    }

    #[Test]
    public function aChosenVariantKeepsItsOwnOfferInTheStructuredData(): void
    {
        $body = (string)$this->executeFrontendSubRequest($this->requestFor([1]))->getBody();

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $body, $match));
        $offers = json_decode($match[1] ?? '', true)['offers'] ?? [];
        $this->assertSame('Offer', $offers['@type'] ?? null);
        $this->assertSame('15.00', $offers['price'] ?? null);
    }

    #[Test]
    public function aSelectedArticleOfAnotherProductIsIgnored(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductVariantSwitchingTest/foreign_article.csv');
        $hashString = '&id=2&tx_productscore_productdetail[product]=1&tx_productscore_productdetail[selectedArticle]=3';
        $request = (new InternalRequest('http://localhost/shop'))->withQueryParameters([
            'tx_productscore_productdetail[product]' => 1,
            'tx_productscore_productdetail[selectedArticle]' => 3,
            'cHash' => $this->get(CacheHashCalculator::class)->generateForParameters($hashString),
        ]);

        $body = (string)$this->executeFrontendSubRequest($request)->getBody();

        $this->assertStringContainsString('T-Shirt', $body);
        $this->assertStringNotContainsString('99.00', $body);
        $this->assertStringContainsString('Please choose a variant', $body);
    }

    /**
     * @param int[] $attributeValueUids
     */
    private function requestFor(array $attributeValueUids = []): InternalRequest
    {
        $queryParameters = ['tx_productscore_productdetail[product]' => 1];
        foreach ($attributeValueUids as $index => $uid) {
            $queryParameters[sprintf('tx_productscore_productdetail[attributeValues][%d]', $index)] = $uid;
        }

        $hashString = '&id=2&tx_productscore_productdetail[product]=1';
        foreach ($attributeValueUids as $index => $uid) {
            $hashString .= sprintf('&tx_productscore_productdetail[attributeValues][%d]=%d', $index, $uid);
        }
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($hashString);
        $queryParameters['cHash'] = $cHash;

        return (new InternalRequest('http://localhost/shop'))->withQueryParameters($queryParameters);
    }
}
