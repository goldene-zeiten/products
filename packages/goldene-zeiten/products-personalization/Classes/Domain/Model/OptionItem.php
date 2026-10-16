<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * One choice of a selection option, e.g. ring size 54; its surcharge adds to the option's, and it alone
 * decides whether choosing it personalizes the goods.
 */
#[Exclude]
class OptionItem extends AbstractEntity
{
    protected string $title = '';
    protected string $value = '';
    /** @var string */
    protected string $surcharge = '0.00';
    protected bool $personalizing = false;

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function setValue(string $value): void
    {
        $this->value = $value;
    }

    public function getSurcharge(): Money
    {
        return Money::fromDecimalString($this->surcharge);
    }

    public function setSurcharge(Money $surcharge): void
    {
        $this->surcharge = $surcharge->getDecimalString();
    }

    /**
     * Whether choosing this makes the goods custom-made, e.g. an engraving font unlike "no engraving".
     */
    public function isPersonalizing(): bool
    {
        return $this->personalizing;
    }

    public function setPersonalizing(bool $personalizing): void
    {
        $this->personalizing = $personalizing;
    }
}
