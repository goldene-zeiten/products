<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Domain\Dto;

use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BasketViewItemDepositTest extends UnitTestCase
{
    #[Test]
    public function depositTotalIsZeroWhenNeitherProductNorArticleHasADeposit(): void
    {
        $item = $this->item(new Product(), null, 3);

        $this->assertSame(0, $item->getDepositTotal()->getCents());
    }

    #[Test]
    public function depositTotalMultipliesTheProductsDepositByQuantity(): void
    {
        $product = new Product();
        $product->setDeposit(Money::fromDecimalString('0.25'));

        $item = $this->item($product, null, 4);

        $this->assertSame(100, $item->getDepositTotal()->getCents());
    }

    #[Test]
    public function depositTotalUsesTheArticlesOwnDepositWhenAnArticleIsSelected(): void
    {
        $product = new Product();
        $product->setDeposit(Money::fromDecimalString('0.25'));
        $article = new Article();
        $article->setDeposit(Money::fromDecimalString('1.50'));

        $item = $this->item($product, $article, 2);

        $this->assertSame(300, $item->getDepositTotal()->getCents());
    }

    #[Test]
    public function basketViewModelSumsDepositAcrossAllItems(): void
    {
        $productA = new Product();
        $productA->setDeposit(Money::fromDecimalString('0.25'));
        $productB = new Product();
        $productB->setDeposit(Money::fromDecimalString('0.50'));

        $basket = new BasketViewModel(
            [$this->item($productA, null, 2), $this->item($productB, null, 1)],
            Money::fromCents(0),
            Money::fromCents(0),
            Money::fromCents(0),
            'EUR'
        );

        $this->assertSame(100, $basket->getDepositTotal()->getCents());
    }

    private function item(Product $product, ?Article $article, int $quantity): BasketViewItem
    {
        $zero = Money::fromCents(0);
        return new BasketViewItem($product, $article, $quantity, $zero, $zero, 0.0, $zero, $zero, $zero);
    }
}
