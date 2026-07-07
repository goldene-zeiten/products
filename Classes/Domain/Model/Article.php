<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

#[Exclude]
class Article extends AbstractEntity
{
    protected ?Product $product = null;
    protected string $title = '';
    protected string $itemNumber = '';
    protected string $ean = '';
    /** @var string */
    protected string $price = '0.00';
    protected int $inStock = 0;
    protected int $weight = 0;
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $images;
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $downloads;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->images = new ObjectStorage();
        $this->downloads = new ObjectStorage();
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): void
    {
        $this->product = $product;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getItemNumber(): string
    {
        return $this->itemNumber;
    }

    public function setItemNumber(string $itemNumber): void
    {
        $this->itemNumber = $itemNumber;
    }

    public function getEan(): string
    {
        return $this->ean;
    }

    public function setEan(string $ean): void
    {
        $this->ean = $ean;
    }

    public function getPrice(): Money
    {
        return Money::fromDecimalString($this->price);
    }

    public function setPrice(Money $price): void
    {
        $this->price = $price->getDecimalString();
    }

    public function getInStock(): int
    {
        return $this->inStock;
    }

    public function setInStock(int $inStock): void
    {
        $this->inStock = $inStock;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getImages(): ObjectStorage
    {
        return $this->images;
    }

    /**
     * @param ObjectStorage<FileReference> $images
     */
    public function setImages(ObjectStorage $images): void
    {
        $this->images = $images;
    }

    /**
     * Own images override the product's gallery; an empty set means "inherit",
     * the same 0.00-inherits convention already used for the article price.
     *
     * @return ObjectStorage<FileReference>
     */
    public function getEffectiveImages(): ObjectStorage
    {
        if ($this->images->count() > 0) {
            return $this->images;
        }
        if ($this->product !== null) {
            return $this->product->getImages();
        }
        /** @var ObjectStorage<FileReference> $empty */
        $empty = new ObjectStorage();
        return $empty;
    }

    /**
     * @return ObjectStorage<FileReference>
     */
    public function getDownloads(): ObjectStorage
    {
        return $this->downloads;
    }

    /**
     * @param ObjectStorage<FileReference> $downloads
     */
    public function setDownloads(ObjectStorage $downloads): void
    {
        $this->downloads = $downloads;
    }
}
