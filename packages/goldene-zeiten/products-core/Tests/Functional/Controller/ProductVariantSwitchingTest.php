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
        yield 'no variant selected shows prompt instead of price' => [
            'attributeValueUids' => [],
            'expectedString' => 'Please choose a variant',
            'unexpectedStrings' => ['15.00', '20.00'],
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
