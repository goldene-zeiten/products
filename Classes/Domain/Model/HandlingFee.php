<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class HandlingFee extends AbstractEntity
{
    protected string $title = '';
    protected string $country = '';
    /** @var string */
    protected string $minOrderValue = '0.00';
    /** @var string */
    protected string $maxOrderValue = '0.00';
    protected int $minWeight = 0;
    protected int $maxWeight = 0;
    /** @var string */
    protected string $rate = '0.00';

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getMinOrderValue(): Money
    {
        return Money::fromDecimalString($this->minOrderValue);
    }

    public function setMinOrderValue(Money $minOrderValue): void
    {
        $this->minOrderValue = $minOrderValue->getDecimalString();
    }

    public function getMaxOrderValue(): Money
    {
        return Money::fromDecimalString($this->maxOrderValue);
    }

    public function setMaxOrderValue(Money $maxOrderValue): void
    {
        $this->maxOrderValue = $maxOrderValue->getDecimalString();
    }

    public function getMinWeight(): int
    {
        return $this->minWeight;
    }

    public function setMinWeight(int $minWeight): void
    {
        $this->minWeight = $minWeight;
    }

    public function getMaxWeight(): int
    {
        return $this->maxWeight;
    }

    public function setMaxWeight(int $maxWeight): void
    {
        $this->maxWeight = $maxWeight;
    }

    public function getRate(): Money
    {
        return Money::fromDecimalString($this->rate);
    }

    public function setRate(Money $rate): void
    {
        $this->rate = $rate->getDecimalString();
    }

    public function isApplicable(int $basketWeight, Money $basketGoodsTotal): bool
    {
        return $this->meetsWeightBounds($basketWeight) && $this->meetsOrderValueBounds($basketGoodsTotal);
    }

    private function meetsWeightBounds(int $basketWeight): bool
    {
        if ($this->minWeight > 0 && $basketWeight < $this->minWeight) {
            return false;
        }
        return $this->maxWeight <= 0 || $basketWeight <= $this->maxWeight;
    }

    private function meetsOrderValueBounds(Money $basketGoodsTotal): bool
    {
        $cents = $basketGoodsTotal->getCents();
        $minCents = $this->getMinOrderValue()->getCents();
        if ($minCents > 0 && $cents < $minCents) {
            return false;
        }
        $maxCents = $this->getMaxOrderValue()->getCents();
        return $maxCents <= 0 || $cents <= $maxCents;
    }
}
