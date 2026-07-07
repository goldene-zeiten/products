<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Pricing\ProductPriceProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProductPriceProviderTest extends UnitTestCase
{
    private ProductPriceProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ProductPriceProvider();
    }

    /**
     * @test
     */
    public function productPriceIsUsedWithoutArticle(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));

        self::assertSame(1999, $this->subject->getUnitPrice($product, null, 1)->getCents());
    }

    /**
     * @test
     */
    public function articlePriceOverridesProductPriceWhenNonZero(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));
        $article = new Article();
        $article->setPrice(Money::fromDecimalString('24.99'));

        self::assertSame(2499, $this->subject->getUnitPrice($product, $article, 1)->getCents());
    }

    /**
     * @test
     */
    public function zeroArticlePriceInheritsProductPrice(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));
        $article = new Article();

        self::assertSame(1999, $this->subject->getUnitPrice($product, $article, 1)->getCents());
    }
}
