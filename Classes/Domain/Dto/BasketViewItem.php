<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Dto;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
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
     * Deposit (e.g. a bottle deposit) is a per-unit amount on top of the price, computed on
     * demand rather than threaded through the constructor since it only depends on data the
     * product/article already carry.
     */
    public function getDepositTotal(): Money
    {
        $deposit = $this->article?->getDeposit() ?? $this->product->getDeposit();
        return $deposit->multiply($this->quantity);
    }

    /**
     * Articles inherit the product's weight, there is no per-article override.
     */
    public function getWeight(): int
    {
        return $this->product->getWeight() * $this->quantity;
    }

    public function isBulky(): bool
    {
        return $this->product->isBulky() || ($this->article?->isBulky() ?? false);
    }
}
