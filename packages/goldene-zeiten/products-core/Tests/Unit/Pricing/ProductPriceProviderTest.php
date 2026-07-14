<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Pricing\ProductPriceProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ProductPriceProviderTest extends UnitTestCase
{
    private ProductPriceProvider $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ProductPriceProvider();
    }

    #[Test]
    public function productPriceIsUsedWithoutArticle(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));

        $this->assertSame(1999, $this->subject->getUnitPrice($product, null, 1)->getCents());
    }

    #[Test]
    public function articlePriceOverridesProductPriceWhenNonZero(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));
        $article = new Article();
        $article->setPrice(Money::fromDecimalString('24.99'));

        $this->assertSame(2499, $this->subject->getUnitPrice($product, $article, 1)->getCents());
    }

    #[Test]
    public function zeroArticlePriceInheritsProductPrice(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));
        $article = new Article();

        $this->assertSame(1999, $this->subject->getUnitPrice($product, $article, 1)->getCents());
    }

    #[Test]
    public function surchargeModeAddsTheArticlePriceOnTopOfTheProductPrice(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));
        $article = new Article();
        $article->setPriceMode('surcharge');
        $article->setPrice(Money::fromDecimalString('5.00'));

        $this->assertSame(2499, $this->subject->getUnitPrice($product, $article, 1)->getCents());
    }

    #[Test]
    public function zeroArticlePriceMeansNoSurchargeEvenInSurchargeMode(): void
    {
        $product = new Product();
        $product->setPrice(Money::fromDecimalString('19.99'));
        $article = new Article();
        $article->setPriceMode('surcharge');

        $this->assertSame(1999, $this->subject->getUnitPrice($product, $article, 1)->getCents());
    }
}
