<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * A quantity-based price tier, IRRE-nested under either a product or an article (never both —
 * whichever parent's inline list created the row owns it; the sibling FK column stays 0).
 */
#[Exclude]
class PriceTier extends AbstractEntity
{
    protected int $minQuantity = 1;
    /** @var string */
    protected string $price = '0.00';

    public function getMinQuantity(): int
    {
        return $this->minQuantity;
    }

    public function setMinQuantity(int $minQuantity): void
    {
        $this->minQuantity = $minQuantity;
    }

    public function getPrice(): Money
    {
        return Money::fromDecimalString($this->price);
    }

    public function setPrice(Money $price): void
    {
        $this->price = $price->getDecimalString();
    }
}
