<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A product priced through its variants is listed at its cheapest variant, not at its own empty price.
 */
final class ProductListVariantPriceTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ProductListVariantPriceTest/list_with_variant_product.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'pids' => [
                            'storageFolder' => 2,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    #[Test]
    public function aProductWithVariantsIsListedFromItsCheapestVariant(): void
    {
        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();

        $this->assertMatchesRegularExpression('#>Ring<.*?<span class="price price-from">\s*From\s+[^<\d]*37\.50#s', $body);
    }

    #[Test]
    public function aProductWithoutVariantsKeepsItsOwnPrice(): void
    {
        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'))->getBody();

        $this->assertMatchesRegularExpression('#>Product 3<.*?<span class="price">[^<\d]*10\.00#s', $body);
    }
}
