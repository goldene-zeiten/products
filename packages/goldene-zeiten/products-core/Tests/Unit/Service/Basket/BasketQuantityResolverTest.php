<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Service\Basket;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Service\Basket\BasketQuantityResolver;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BasketQuantityResolverTest extends UnitTestCase
{
    private BasketQuantityResolver $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new BasketQuantityResolver();
    }

    #[Test]
    public function unconfiguredProductHasNoBounds(): void
    {
        $product = new Product();

        $this->assertSame(3, $this->subject->clamp($product, null, 3));
    }

    #[Test]
    public function quantityBelowProductMinimumIsRaised(): void
    {
        $product = new Product();
        $product->setBasketMinQuantity(5);

        $this->assertSame(5, $this->subject->clamp($product, null, 2));
    }

    #[Test]
    public function quantityAboveProductMaximumIsLowered(): void
    {
        $product = new Product();
        $product->setBasketMaxQuantity(4);

        $this->assertSame(4, $this->subject->clamp($product, null, 10));
    }

    #[Test]
    public function nonZeroArticleBoundsOverrideTheProducts(): void
    {
        $product = new Product();
        $product->setBasketMinQuantity(5);
        $product->setBasketMaxQuantity(10);
        $article = new Article();
        $article->setBasketMinQuantity(1);
        $article->setBasketMaxQuantity(2);

        $this->assertSame(2, $this->subject->clamp($product, $article, 3));
    }

    #[Test]
    public function zeroArticleBoundsInheritTheProducts(): void
    {
        $product = new Product();
        $product->setBasketMaxQuantity(4);
        $article = new Article();

        $this->assertSame(4, $this->subject->clamp($product, $article, 10));
    }
}
