<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Model;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

#[Exclude]
class Category extends AbstractEntity
{
    protected string $title = '';
    protected string $slug = '';
    protected string $description = '';
    protected string $notificationEmail = '';
    protected string $notificationRecipientName = '';
    protected ?Category $parentCategory = null;
    /**
     * @var ObjectStorage<Category>
     */
    protected ObjectStorage $subCategories;
    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $image;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->subCategories = new ObjectStorage();
        $this->image = new ObjectStorage();
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

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): void
    {
        $this->description = $description;
    }

    public function getNotificationEmail(): string
    {
        return $this->notificationEmail;
    }

    public function setNotificationEmail(string $notificationEmail): void
    {
        $this->notificationEmail = $notificationEmail;
    }

    public function getNotificationRecipientName(): string
    {
        return $this->notificationRecipientName;
    }

    public function setNotificationRecipientName(string $notificationRecipientName): void
    {
        $this->notificationRecipientName = $notificationRecipientName;
    }

    public function getParentCategory(): ?Category
    {
        return $this->parentCategory;
    }

    public function setParentCategory(?Category $parentCategory): void
    {
        $this->parentCategory = $parentCategory;
    }

    /**
     * @return ObjectStorage<Category>
     */
    public function getSubCategories(): ObjectStorage
    {
        return $this->subCategories;
    }

    /**
     * @param ObjectStorage<Category> $subCategories
     */
    public function setSubCategories(ObjectStorage $subCategories): void
    {
        $this->subCategories = $subCategories;
    }

    public function getImage(): ?FileReference
    {
        foreach ($this->image as $image) {
            return $image;
        }
        return null;
    }

    /**
     * @param ObjectStorage<FileReference> $image
     */
    public function setImage(ObjectStorage $image): void
    {
        $this->image = $image;
    }
}
