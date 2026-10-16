<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * An option a customer chooses for a product or article: a selection list (ring size) or a free text
 * (engraving). Surcharges are per unit, in the shop's pricing mode like product prices.
 */
#[Exclude]
class Option extends AbstractEntity
{
    public const TYPE_SELECT = 'select';
    public const TYPE_TEXT = 'text';
    public const CHARACTER_SET_ENGRAVING = 'engraving';

    protected int $product = 0;
    protected int $article = 0;
    protected string $type = self::TYPE_SELECT;
    protected string $title = '';
    protected string $identifier = '';
    protected bool $required = false;
    protected bool $personalizing = true;
    /** @var string */
    protected string $surcharge = '0.00';
    protected int $maxLength = 0;
    protected string $characterSet = '';

    /**
     * @var ObjectStorage<OptionItem>
     */
    protected ObjectStorage $items;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->items = new ObjectStorage();
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

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        $this->type = $type;
    }

    public function isText(): bool
    {
        return $this->type === self::TYPE_TEXT;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getIdentifier(): string
    {
        return $this->identifier;
    }

    public function setIdentifier(string $identifier): void
    {
        $this->identifier = $identifier;
    }

    public function isRequired(): bool
    {
        return $this->required;
    }

    public function setRequired(bool $required): void
    {
        $this->required = $required;
    }

    /**
     * For free texts; a choice of a selection list decides this itself.
     */
    public function isPersonalizing(): bool
    {
        return $this->personalizing;
    }

    public function setPersonalizing(bool $personalizing): void
    {
        $this->personalizing = $personalizing;
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
     * 0 allows a text of any length.
     */
    public function getMaxLength(): int
    {
        return $this->maxLength;
    }

    public function setMaxLength(int $maxLength): void
    {
        $this->maxLength = $maxLength;
    }

    public function getCharacterSet(): string
    {
        return $this->characterSet;
    }

    public function setCharacterSet(string $characterSet): void
    {
        $this->characterSet = $characterSet;
    }

    /**
     * @return ObjectStorage<OptionItem>
     */
    public function getItems(): ObjectStorage
    {
        return $this->items;
    }

    /**
     * @param ObjectStorage<OptionItem> $items
     */
    public function setItems(ObjectStorage $items): void
    {
        $this->items = $items;
    }

    public function findItem(string $value): ?OptionItem
    {
        foreach ($this->items as $item) {
            if ($item->getValue() === $value) {
                return $item;
            }
        }
        return null;
    }
}
