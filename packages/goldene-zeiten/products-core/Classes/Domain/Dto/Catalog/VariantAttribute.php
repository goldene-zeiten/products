<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Catalog;

use GoldeneZeiten\Products\Core\Domain\Model\Attribute;
use GoldeneZeiten\Products\Core\Domain\Model\AttributeValue;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * An attribute a product's variants differ in, with only the values its articles carry, in the attribute's
 * order - what a variant selector offers.
 */
#[Exclude]
final readonly class VariantAttribute
{
    /**
     * @param AttributeValue[] $values
     */
    public function __construct(
        private Attribute $attribute,
        private array $values
    ) {}

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getUid(): ?int
    {
        return $this->attribute->getUid();
    }

    public function getTitle(): string
    {
        return $this->attribute->getTitle();
    }

    /**
     * @return AttributeValue[]
     */
    public function getValues(): array
    {
        return $this->values;
    }
}
