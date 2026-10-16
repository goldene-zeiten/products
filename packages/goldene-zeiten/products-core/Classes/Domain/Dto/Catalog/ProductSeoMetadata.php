<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Catalog;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;

/**
 * The search engine and social media texts a product detail page uses, with the article's overrides and
 * the fallbacks already applied. An empty title leaves the page title to the configured title mode.
 */
#[Exclude]
final readonly class ProductSeoMetadata
{
    public function __construct(
        public string $title,
        public string $description,
        public string $keywords,
        public bool $noIndex,
        public string $ogTitle,
        public string $ogDescription,
        public ?FileReference $ogImage,
    ) {}
}
