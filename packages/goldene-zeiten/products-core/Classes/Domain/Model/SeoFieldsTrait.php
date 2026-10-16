<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * The search engine and social media texts of a product, which an article can override field by field. The
 * using model initializes $ogImage in its initializeObject().
 */
trait SeoFieldsTrait
{
    protected string $seoTitle = '';
    protected string $metaDescription = '';
    protected string $keywords = '';
    protected bool $noIndex = false;
    protected string $ogTitle = '';
    protected string $ogDescription = '';

    /**
     * @var ObjectStorage<FileReference>
     */
    protected ObjectStorage $ogImage;

    public function getSeoTitle(): string
    {
        return $this->seoTitle;
    }

    public function getMetaDescription(): string
    {
        return $this->metaDescription;
    }

    public function getKeywords(): string
    {
        return $this->keywords;
    }

    public function isNoIndex(): bool
    {
        return $this->noIndex;
    }

    public function getOgTitle(): string
    {
        return $this->ogTitle;
    }

    public function getOgDescription(): string
    {
        return $this->ogDescription;
    }

    public function getOgImage(): ?FileReference
    {
        foreach ($this->ogImage as $image) {
            return $image;
        }
        return null;
    }
}
