<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

#[Exclude]
class TaxRate extends AbstractEntity
{
    protected ?TaxClass $taxClass = null;
    protected string $country = '';
    protected float $rate = 0.0;
    protected ?\DateTime $validFrom = null;
    protected ?\DateTime $validUntil = null;

    public function getTaxClass(): ?TaxClass
    {
        return $this->taxClass;
    }

    public function setTaxClass(?TaxClass $taxClass): void
    {
        $this->taxClass = $taxClass;
    }

    public function getCountry(): string
    {
        return $this->country;
    }

    public function setCountry(string $country): void
    {
        $this->country = $country;
    }

    public function getRate(): float
    {
        return $this->rate;
    }

    public function setRate(float $rate): void
    {
        $this->rate = $rate;
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
}
