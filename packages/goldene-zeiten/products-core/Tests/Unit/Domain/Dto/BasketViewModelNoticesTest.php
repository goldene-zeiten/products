<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Domain\Dto;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BasketViewModelNoticesTest extends UnitTestCase
{
    #[Test]
    public function hasBulkyItemIsFalseWhenNothingIsFlagged(): void
    {
        $basket = $this->basketOf($this->item(new Product(), null, 1));

        $this->assertFalse($basket->hasBulkyItem());
    }

    #[Test]
    public function hasBulkyItemIsTrueWhenTheProductIsFlagged(): void
    {
        $product = new Product();
        $product->setBulky(true);

        $basket = $this->basketOf($this->item($product, null, 1));

        $this->assertTrue($basket->hasBulkyItem());
    }

    #[Test]
    public function hasBulkyItemIsTrueWhenOnlyTheSelectedArticleIsFlagged(): void
    {
        $article = new Article();
        $article->setBulky(true);

        $basket = $this->basketOf($this->item(new Product(), $article, 1));

        $this->assertTrue($basket->hasBulkyItem());
    }

    #[Test]
    public function totalWeightSumsEachLinesWeightByQuantity(): void
    {
        $productA = new Product();
        $productA->setWeight(500);
        $productB = new Product();
        $productB->setWeight(200);

        $basket = $this->basketOf($this->item($productA, null, 2), $this->item($productB, null, 3));

        $this->assertSame(1000 + 600, $basket->getTotalWeight());
    }

    #[Test]
    public function articleWeightIsAlwaysInheritedFromTheProduct(): void
    {
        $product = new Product();
        $product->setWeight(300);

        $basket = $this->basketOf($this->item($product, new Article(), 2));

        $this->assertSame(600, $basket->getTotalWeight());
    }

    private function basketOf(BasketViewItem ...$items): BasketViewModel
    {
        $zero = Money::fromCents(0);
        return new BasketViewModel($items, $zero, $zero, $zero, 'EUR');
    }

    private function item(Product $product, ?Article $article, int $quantity): BasketViewItem
    {
        $zero = Money::fromCents(0);
        return new BasketViewItem($product, $article, $quantity, $zero, $zero, 0.0, $zero, $zero, $zero);
    }
}
