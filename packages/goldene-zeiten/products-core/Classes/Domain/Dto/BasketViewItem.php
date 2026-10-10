<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

#[Exclude]
final readonly class BasketViewItem
{
    public function __construct(
        private Product $product,
        private ?Article $article,
        private int $quantity,
        private Money $unitPriceNet,
        private Money $unitPriceGross,
        private float $taxRate,
        private Money $lineTotalNet,
        private Money $lineTotalGross,
        private Money $lineTotalTax
    ) {}

    public function getProduct(): Product
    {
        return $this->product;
    }

    public function getArticle(): ?Article
    {
        return $this->article;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getUnitPriceNet(): Money
    {
        return $this->unitPriceNet;
    }

    public function getUnitPriceGross(): Money
    {
        return $this->unitPriceGross;
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function getLineTotalNet(): Money
    {
        return $this->lineTotalNet;
    }

    public function getLineTotalGross(): Money
    {
        return $this->lineTotalGross;
    }

    public function getLineTotalTax(): Money
    {
        return $this->lineTotalTax;
    }

    /**
     * An article without a deposit of its own (0) carries the product's.
     */
    public function getUnitDeposit(): Money
    {
        $articleDeposit = $this->article?->getDeposit();
        return $articleDeposit !== null && $articleDeposit->getCents() > 0 ? $articleDeposit : $this->product->getDeposit();
    }

    public function getDepositTotal(): Money
    {
        return $this->getUnitDeposit()->multiply($this->quantity);
    }

    /**
     * Weight of one unit in grams; an article without a weight of its own (0) weighs what the product does.
     */
    public function getUnitWeight(): int
    {
        $articleWeight = $this->article?->getWeight() ?? 0;
        return $articleWeight > 0 ? $articleWeight : $this->product->getWeight();
    }

    /**
     * Weight of the whole line in grams.
     */
    public function getWeight(): int
    {
        return $this->getUnitWeight() * $this->quantity;
    }

    public function isBulky(): bool
    {
        return $this->product->isBulky() || ($this->article?->isBulky() ?? false);
    }
}
