<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Model;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

#[Exclude]
class Article extends AbstractEntity
{
    protected ?Product $product = null;
    protected string $title = '';
    protected string $slug = '';
    protected string $itemNumber = '';
    protected string $ean = '';
    /** @var string */
    protected string $price = '0.00';
    protected string $priceMode = 'override';
    /** @var string */
    protected string $directCost = '0.00';
    /** @var string */
    protected string $deposit = '0.00';
    protected int $inStock = 0;
    protected bool $unlimitedStock = false;
    protected int $basketMinQuantity = 0;
    protected int $basketMaxQuantity = 0;
    protected int $weight = 0;
    protected bool $bulky = false;
    protected float $contentAmount = 0.0;
    protected string $contentUnit = '';
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $images;
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $downloads;
    /**
     * @var ObjectStorage<PriceTier>
     */
    protected ObjectStorage $priceTiers;
    /**
     * @var ObjectStorage<PricePeriod>
     */
    protected ObjectStorage $pricePeriods;
    /**
     * @var ObjectStorage<AttributeValue>
     */
    protected ObjectStorage $attributeValues;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->images = new ObjectStorage();
        $this->downloads = new ObjectStorage();
        $this->priceTiers = new ObjectStorage();
        $this->pricePeriods = new ObjectStorage();
        $this->attributeValues = new ObjectStorage();
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

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
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

    public function getPriceMode(): string
    {
        return $this->priceMode;
    }

    public function setPriceMode(string $priceMode): void
    {
        $this->priceMode = $priceMode;
    }

    public function getDirectCost(): Money
    {
        return Money::fromDecimalString($this->directCost);
    }

    public function setDirectCost(Money $directCost): void
    {
        $this->directCost = $directCost->getDecimalString();
    }

    public function getDeposit(): Money
    {
        return Money::fromDecimalString($this->deposit);
    }

    public function setDeposit(Money $deposit): void
    {
        $this->deposit = $deposit->getDecimalString();
    }

    public function getInStock(): int
    {
        return $this->inStock;
    }

    public function setInStock(int $inStock): void
    {
        $this->inStock = $inStock;
    }

    public function isUnlimitedStock(): bool
    {
        return $this->unlimitedStock;
    }

    public function setUnlimitedStock(bool $unlimitedStock): void
    {
        $this->unlimitedStock = $unlimitedStock;
    }

    public function getBasketMinQuantity(): int
    {
        return $this->basketMinQuantity;
    }

    public function setBasketMinQuantity(int $basketMinQuantity): void
    {
        $this->basketMinQuantity = $basketMinQuantity;
    }

    public function getBasketMaxQuantity(): int
    {
        return $this->basketMaxQuantity;
    }

    public function setBasketMaxQuantity(int $basketMaxQuantity): void
    {
        $this->basketMaxQuantity = $basketMaxQuantity;
    }

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(int $weight): void
    {
        $this->weight = $weight;
    }

    public function isBulky(): bool
    {
        return $this->bulky;
    }

    public function setBulky(bool $bulky): void
    {
        $this->bulky = $bulky;
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

    /**
     * @return ObjectStorage<PriceTier>
     */
    public function getPriceTiers(): ObjectStorage
    {
        return $this->priceTiers;
    }

    /**
     * @param ObjectStorage<PriceTier> $priceTiers
     */
    public function setPriceTiers(ObjectStorage $priceTiers): void
    {
        $this->priceTiers = $priceTiers;
    }

    /**
     * @return ObjectStorage<PricePeriod>
     */
    public function getPricePeriods(): ObjectStorage
    {
        return $this->pricePeriods;
    }

    /**
     * @param ObjectStorage<PricePeriod> $pricePeriods
     */
    public function setPricePeriods(ObjectStorage $pricePeriods): void
    {
        $this->pricePeriods = $pricePeriods;
    }

    /**
     * @return ObjectStorage<AttributeValue>
     */
    public function getAttributeValues(): ObjectStorage
    {
        return $this->attributeValues;
    }

    /**
     * @param ObjectStorage<AttributeValue> $attributeValues
     */
    public function setAttributeValues(ObjectStorage $attributeValues): void
    {
        $this->attributeValues = $attributeValues;
    }

    public function getContentAmount(): float
    {
        return $this->contentAmount;
    }

    public function setContentAmount(float $contentAmount): void
    {
        $this->contentAmount = $contentAmount;
    }

    public function getContentUnit(): string
    {
        return $this->contentUnit;
    }

    public function setContentUnit(string $contentUnit): void
    {
        $this->contentUnit = $contentUnit;
    }
}
