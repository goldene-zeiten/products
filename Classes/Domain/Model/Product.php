<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use GoldeneZeiten\Products\Domain\ValueObject\Money;
use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

#[Exclude]
class Product extends AbstractEntity
{
    protected string $title = '';
    protected string $subtitle = '';
    protected string $slug = '';
    protected string $description = '';
    protected string $itemNumber = '';
    protected string $ean = '';
    /** @var string */
    protected string $price = '0.00';
    /** @var string */
    protected string $directCost = '0.00';
    /** @var string */
    protected string $deposit = '0.00';
    protected ?TaxClass $taxClass = null;
    /**
     * @var ObjectStorage<Category>
     */
    protected ObjectStorage $categories;
    protected int $inStock = 0;
    protected int $basketMinQuantity = 0;
    protected int $basketMaxQuantity = 0;
    protected int $weight = 0;
    protected bool $bulky = false;
    protected int $creditPoints = 0;
    protected float $discountPercent = 0.0;
    protected bool $discountDisabled = false;
    protected bool $isOffer = false;
    protected bool $isHighlight = false;
    /**
     * @var ObjectStorage<Article>
     */
    protected ObjectStorage $articles;
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
     * @var ObjectStorage<Product>
     */
    protected ObjectStorage $relatedProducts;
    /**
     * @var ObjectStorage<Product>
     */
    protected ObjectStorage $accessoryProducts;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->categories = new ObjectStorage();
        $this->articles = new ObjectStorage();
        $this->images = new ObjectStorage();
        $this->downloads = new ObjectStorage();
        $this->priceTiers = new ObjectStorage();
        $this->relatedProducts = new ObjectStorage();
        $this->accessoryProducts = new ObjectStorage();
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function getSubtitle(): string
    {
        return $this->subtitle;
    }

    public function setSubtitle(string $subtitle): void
    {
        $this->subtitle = $subtitle;
    }

    public function getSlug(): string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): void
    {
        $this->slug = $slug;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
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

    public function getTaxClass(): ?TaxClass
    {
        return $this->taxClass;
    }

    public function setTaxClass(?TaxClass $taxClass): void
    {
        $this->taxClass = $taxClass;
    }

    /**
     * @return ObjectStorage<Category>
     */
    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    /**
     * @param ObjectStorage<Category> $categories
     */
    public function setCategories(ObjectStorage $categories): void
    {
        $this->categories = $categories;
    }

    public function getInStock(): int
    {
        return $this->inStock;
    }

    public function setInStock(int $inStock): void
    {
        $this->inStock = $inStock;
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

    public function getCreditPoints(): int
    {
        return $this->creditPoints;
    }

    public function setCreditPoints(int $creditPoints): void
    {
        $this->creditPoints = $creditPoints;
    }

    public function getDiscountPercent(): float
    {
        return $this->discountPercent;
    }

    public function setDiscountPercent(float $discountPercent): void
    {
        $this->discountPercent = $discountPercent;
    }

    public function isDiscountDisabled(): bool
    {
        return $this->discountDisabled;
    }

    public function setDiscountDisabled(bool $discountDisabled): void
    {
        $this->discountDisabled = $discountDisabled;
    }

    public function isOffer(): bool
    {
        return $this->isOffer;
    }

    public function setIsOffer(bool $isOffer): void
    {
        $this->isOffer = $isOffer;
    }

    public function isHighlight(): bool
    {
        return $this->isHighlight;
    }

    public function setIsHighlight(bool $isHighlight): void
    {
        $this->isHighlight = $isHighlight;
    }

    /**
     * @return ObjectStorage<Article>
     */
    public function getArticles(): ObjectStorage
    {
        return $this->articles;
    }

    /**
     * @param ObjectStorage<Article> $articles
     */
    public function setArticles(ObjectStorage $articles): void
    {
        $this->articles = $articles;
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

    public function getPrimaryImage(): ?FileReference
    {
        foreach ($this->images as $image) {
            return $image;
        }
        return null;
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
     * The cheapest configured tier, used for "from X" list-view pricing.
     */
    public function getLowestPriceTier(): ?PriceTier
    {
        $lowest = null;
        foreach ($this->priceTiers as $tier) {
            if ($lowest === null || $tier->getPrice()->getCents() < $lowest->getPrice()->getCents()) {
                $lowest = $tier;
            }
        }
        return $lowest;
    }

    /**
     * Distinct attributes used by at least one article, for building a variant selector.
     *
     * @return Attribute[]
     */
    public function getVariantAttributes(): array
    {
        $seen = [];
        foreach ($this->articles as $article) {
            foreach ($article->getAttributeValues() as $value) {
                $attribute = $value->getAttribute();
                if ($attribute?->getUid() !== null) {
                    $seen[$attribute->getUid()] = $attribute;
                }
            }
        }
        return array_values($seen);
    }

    /**
     * @return ObjectStorage<Product>
     */
    public function getRelatedProducts(): ObjectStorage
    {
        return $this->relatedProducts;
    }

    /**
     * @param ObjectStorage<Product> $relatedProducts
     */
    public function setRelatedProducts(ObjectStorage $relatedProducts): void
    {
        $this->relatedProducts = $relatedProducts;
    }

    /**
     * @return ObjectStorage<Product>
     */
    public function getAccessoryProducts(): ObjectStorage
    {
        return $this->accessoryProducts;
    }

    /**
     * @param ObjectStorage<Product> $accessoryProducts
     */
    public function setAccessoryProducts(ObjectStorage $accessoryProducts): void
    {
        $this->accessoryProducts = $accessoryProducts;
    }
}
