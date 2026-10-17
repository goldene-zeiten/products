<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * An attribute is shared by many products, but the variant selector of one product offers only the values its
 * own articles carry - any other value leads to no article.
 */
final class ProductVariantValuesTest extends AbstractFrontendTestCase
{
    #[Test]
    public function theSelectorOffersOnlyTheValuesOfTheProductsArticles(): void
    {
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductVariantValuesTest/product_with_unused_value.csv');
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters('&id=2&tx_productscore_productdetail[product]=1');

        $body = (string)$this->executeFrontendSubRequest((new InternalRequest('http://localhost/shop'))->withQueryParameters([
            'tx_productscore_productdetail[product]' => 1,
            'cHash' => $cHash,
        ]))->getBody();

        $this->assertMatchesRegularExpression('#<option value="1">Small</option>#', $body);
        $this->assertMatchesRegularExpression('#<option value="2">Large</option>#', $body);
        $this->assertStringNotContainsString('Watch movement', $body);
    }
}
