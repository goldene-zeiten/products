<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class ProductVariantSwitchingTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/product_with_variants.csv');
    }

    #[Test]
    public function noVariantSelectedShowsThePromptInsteadOfAPriceOrAddToBasketForm(): void
    {
        $response = $this->executeFrontendSubRequest($this->requestFor());

        $body = (string)$response->getBody();
        $this->assertStringContainsString('Please choose a variant', $body);
        $this->assertStringNotContainsString('15.00', $body);
        $this->assertStringNotContainsString('20.00', $body);
    }

    #[Test]
    public function selectingASmallVariantShowsItsOwnPriceAndStock(): void
    {
        $response = $this->executeFrontendSubRequest($this->requestFor([1]));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('15.00', $body);
        $this->assertStringNotContainsString('20.00', $body);
    }

    #[Test]
    public function selectingALargeOutOfStockVariantShowsItsOwnPriceAndOutOfStockState(): void
    {
        $response = $this->executeFrontendSubRequest($this->requestFor([2]));

        $body = (string)$response->getBody();
        $this->assertStringContainsString('20.00', $body);
        $this->assertStringNotContainsString('15.00', $body);
        $this->assertMatchesRegularExpression('/out.of.stock|Out of Stock/i', $body);
    }

    /**
     * @param int[] $attributeValueUids
     */
    private function requestFor(array $attributeValueUids = []): InternalRequest
    {
        $queryParameters = ['tx_products_productdetail[product]' => 1];
        foreach ($attributeValueUids as $index => $uid) {
            $queryParameters[sprintf('tx_products_productdetail[attributeValues][%d]', $index)] = $uid;
        }

        $hashString = '&id=2&tx_products_productdetail[product]=1';
        foreach ($attributeValueUids as $index => $uid) {
            $hashString .= sprintf('&tx_products_productdetail[attributeValues][%d]=%d', $index, $uid);
        }
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($hashString);
        $queryParameters['cHash'] = $cHash;

        return (new InternalRequest('http://localhost/shop'))->withQueryParameters($queryParameters);
    }
}
