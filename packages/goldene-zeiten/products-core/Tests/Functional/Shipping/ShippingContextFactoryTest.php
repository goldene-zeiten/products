<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Shipping\ShippingContextFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class ShippingContextFactoryTest extends AbstractFunctionalTestCase
{
    #[Test]
    #[DataProvider('weightDataProvider')]
    public function itemWeightIsPerUnitAndTheTotalCoversTheQuantity(?int $articleWeight, int $expectedUnitWeight): void
    {
        $subject = $this->get(ShippingContextFactory::class);
        $product = new Product();
        $product->setWeight(500);
        $article = null;
        if ($articleWeight !== null) {
            $article = new Article();
            $article->setWeight($articleWeight);
        }

        $context = $subject->createFromBasket($this->basket(new BasketViewItem(...$this->itemArguments($product, $article, 3))), new Address(country: 'DE'));

        $this->assertSame($expectedUnitWeight, $context->getItems()[0]->getWeight());
        $this->assertSame(3 * $expectedUnitWeight, $context->getTotalWeight());
    }

    public static function weightDataProvider(): \Generator
    {
        yield 'a product without article' => [
            'articleWeight' => null,
            'expectedUnitWeight' => 500,
        ];
        yield 'an article without own weight inherits the product weight' => [
            'articleWeight' => 0,
            'expectedUnitWeight' => 500,
        ];
        yield 'an article with own weight uses it' => [
            'articleWeight' => 800,
            'expectedUnitWeight' => 800,
        ];
    }

    #[Test]
    #[DataProvider('depositDataProvider')]
    public function articleDepositFallsBackToTheProductDeposit(string $articleDeposit, int $expectedCents): void
    {
        $product = new Product();
        $product->setDeposit(Money::fromDecimalString('0.25'));
        $article = new Article();
        $article->setDeposit(Money::fromDecimalString($articleDeposit));

        $basket = $this->basket(new BasketViewItem(...$this->itemArguments($product, $article, 2)));

        $this->assertSame($expectedCents, $basket->getDepositTotal()->getCents());
    }

    public static function depositDataProvider(): \Generator
    {
        yield 'an article without own deposit inherits the product deposit' => [
            'articleDeposit' => '0.00',
            'expectedCents' => 50,
        ];
        yield 'an article with own deposit uses it' => [
            'articleDeposit' => '0.15',
            'expectedCents' => 30,
        ];
    }

    private function basket(BasketViewItem $item): BasketViewModel
    {
        $zero = Money::fromCents(0);
        return new BasketViewModel([$item], $zero, $zero, $zero, 'EUR');
    }

    /**
     * @return array{Product, ?Article, int, Money, Money, float, Money, Money, Money}
     */
    private function itemArguments(Product $product, ?Article $article, int $quantity): array
    {
        $zero = Money::fromCents(0);
        return [$product, $article, $quantity, $zero, $zero, 0.0, $zero, $zero, $zero];
    }
}
