<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * A variant dimension (e.g. "Size", "Colour") an article can carry one value of.
 */
#[Exclude]
class Attribute extends AbstractEntity
{
    protected string $title = '';
    /**
     * @var ObjectStorage<AttributeValue>
     */
    protected ObjectStorage $values;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->values = new ObjectStorage();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    /**
     * @return ObjectStorage<AttributeValue>
     */
    public function getValues(): ObjectStorage
    {
        return $this->values;
    }

    /**
     * @param ObjectStorage<AttributeValue> $values
     */
    public function setValues(ObjectStorage $values): void
    {
        $this->values = $values;
    }
}
