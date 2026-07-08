<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class OrderItem extends AbstractEntity
{
    protected ?Order $parentOrder = null;
    protected int $product = 0;
    protected int $article = 0;
    protected string $title = '';
    protected string $articleTitle = '';
    protected string $itemNumber = '';
    protected int $quantity = 0;
    /** @var int */
    protected int $unitPriceNet = 0;
    /** @var int */
    protected int $unitPriceGross = 0;
    protected float $taxRate = 0.0;
    /** @var int */
    protected int $lineTotalNet = 0;
    /** @var int */
    protected int $lineTotalTax = 0;
    /** @var int */
    protected int $lineTotalGross = 0;
    /** @var int */
    protected int $depositTotal = 0;
    /** @var string */
    protected string $options = '[]';

    public function getParentOrder(): ?Order
    {
        return $this->parentOrder;
    }

    public function setParentOrder(?Order $parentOrder): void
    {
        $this->parentOrder = $parentOrder;
    }

    public function getProduct(): int
    {
        return $this->product;
    }

    public function setProduct(int $product): void
    {
        $this->product = $product;
    }

    public function getArticle(): int
    {
        return $this->article;
    }

    public function setArticle(int $article): void
    {
        $this->article = $article;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getArticleTitle(): string
    {
        return $this->articleTitle;
    }

    public function setArticleTitle(string $articleTitle): void
    {
        $this->articleTitle = $articleTitle;
    }

    public function getItemNumber(): string
    {
        return $this->itemNumber;
    }

    public function setItemNumber(string $itemNumber): void
    {
        $this->itemNumber = $itemNumber;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): void
    {
        $this->quantity = $quantity;
    }

    public function getUnitPriceNet(): Money
    {
        return Money::fromCents($this->unitPriceNet);
    }

    public function setUnitPriceNet(Money $unitPriceNet): void
    {
        $this->unitPriceNet = $unitPriceNet->getCents();
    }

    public function getUnitPriceGross(): Money
    {
        return Money::fromCents($this->unitPriceGross);
    }

    public function setUnitPriceGross(Money $unitPriceGross): void
    {
        $this->unitPriceGross = $unitPriceGross->getCents();
    }

    public function getTaxRate(): float
    {
        return $this->taxRate;
    }

    public function setTaxRate(float $taxRate): void
    {
        $this->taxRate = $taxRate;
    }

    public function getLineTotalNet(): Money
    {
        return Money::fromCents($this->lineTotalNet);
    }

    public function setLineTotalNet(Money $lineTotalNet): void
    {
        $this->lineTotalNet = $lineTotalNet->getCents();
    }

    public function getLineTotalTax(): Money
    {
        return Money::fromCents($this->lineTotalTax);
    }

    public function setLineTotalTax(Money $lineTotalTax): void
    {
        $this->lineTotalTax = $lineTotalTax->getCents();
    }

    public function getLineTotalGross(): Money
    {
        return Money::fromCents($this->lineTotalGross);
    }

    public function setLineTotalGross(Money $lineTotalGross): void
    {
        $this->lineTotalGross = $lineTotalGross->getCents();
    }

    public function getDepositTotal(): Money
    {
        return Money::fromCents($this->depositTotal);
    }

    public function setDepositTotal(Money $depositTotal): void
    {
        $this->depositTotal = $depositTotal->getCents();
    }

    /**
     * @return array<string, mixed>
     */
    public function getOptions(): array
    {
        return json_decode($this->options, true) ?: [];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function setOptions(array $options): void
    {
        $this->options = (string)json_encode($options);
    }
}
