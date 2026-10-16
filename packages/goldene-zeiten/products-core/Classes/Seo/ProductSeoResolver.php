<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Seo;

use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductSeoMetadata;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;

/**
 * Resolves the SEO texts of a product detail page: an article's own value wins, then the product's, then a
 * value derived from the product - the description from its text, Open Graph from the SEO texts and the
 * title, the image from its first picture.
 */
final class ProductSeoResolver
{
    private const DESCRIPTION_LENGTH = 150;

    public function resolve(Product $product, ?Article $article): ProductSeoMetadata
    {
        $title = $this->first($article?->getSeoTitle(), $product->getSeoTitle());
        $description = $this->first($article?->getMetaDescription(), $product->getMetaDescription())
            ?: $this->excerpt($product->getDescription());
        return new ProductSeoMetadata(
            $title,
            $description,
            $this->first($article?->getKeywords(), $product->getKeywords()),
            $product->isNoIndex() || $article?->isNoIndex() === true,
            $this->first($article?->getOgTitle(), $product->getOgTitle(), $title, $product->getTitle()),
            $this->first($article?->getOgDescription(), $product->getOgDescription(), $description),
            $article?->getOgImage() ?? $product->getOgImage() ?? $product->getPrimaryImage(),
        );
    }

    private function first(?string ...$values): string
    {
        foreach ($values as $value) {
            if (trim((string)$value) !== '') {
                return trim((string)$value);
            }
        }
        return '';
    }

    /**
     * The product text without markup, cut at a word boundary.
     */
    private function excerpt(string $html): string
    {
        $text = trim((string)preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5)));
        if (mb_strlen($text) <= self::DESCRIPTION_LENGTH) {
            return $text;
        }
        $cut = mb_substr($text, 0, self::DESCRIPTION_LENGTH);
        $lastSpace = mb_strrpos($cut, ' ');
        return rtrim($lastSpace !== false ? mb_substr($cut, 0, $lastSpace) : $cut, ' ,.;:') . '…';
    }
}
