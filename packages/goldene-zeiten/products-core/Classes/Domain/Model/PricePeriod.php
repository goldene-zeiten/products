<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class PricePeriod extends AbstractEntity
{
    /** @var string */
    protected string $price = '0.00';
    protected int $feGroup = 0;
    protected ?\DateTime $validFrom = null;
    protected ?\DateTime $validUntil = null;
    protected string $note = '';

    public function getPrice(): Money
    {
        return Money::fromDecimalString($this->price);
    }

    public function setPrice(Money $price): void
    {
        $this->price = $price->getDecimalString();
    }

    public function isPublic(): bool
    {
        return $this->feGroup === 0;
    }

    public function getFeGroup(): int
    {
        return $this->feGroup;
    }

    public function setFeGroup(int $feGroup): void
    {
        $this->feGroup = $feGroup;
    }

    public function getValidFrom(): ?\DateTime
    {
        return $this->validFrom;
    }

    public function setValidFrom(?\DateTime $validFrom): void
    {
        $this->validFrom = $validFrom;
    }

    public function getValidUntil(): ?\DateTime
    {
        return $this->validUntil;
    }

    public function setValidUntil(?\DateTime $validUntil): void
    {
        $this->validUntil = $validUntil;
    }

    public function getNote(): string
    {
        return $this->note;
    }

    public function setNote(string $note): void
    {
        $this->note = $note;
    }
}
