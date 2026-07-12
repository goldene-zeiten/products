<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class PriceHistoryEntry extends AbstractEntity
{
    /** @var string */
    protected string $price = '0.00';
    protected ?\DateTime $validFrom = null;
    protected ?\DateTime $validUntil = null;
    protected ?\DateTime $recordedAt = null;

    public function getPrice(): Money
    {
        return Money::fromDecimalString($this->price);
    }

    public function setPrice(Money $price): void
    {
        $this->price = $price->getDecimalString();
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

    public function getRecordedAt(): ?\DateTime
    {
        return $this->recordedAt;
    }

    public function setRecordedAt(?\DateTime $recordedAt): void
    {
        $this->recordedAt = $recordedAt;
    }
}
