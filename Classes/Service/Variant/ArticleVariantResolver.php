<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Variant;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;

/**
 * Match attribute-value UIDs to an article (no-JS fallback).
 */
final class ArticleVariantResolver
{
    /**
     * @param int[] $selectedAttributeValueUids
     */
    public function resolve(Product $product, array $selectedAttributeValueUids): ?Article
    {
        $selected = $selectedAttributeValueUids;
        sort($selected);
        foreach ($product->getArticles() as $article) {
            if ($this->articleMatches($article, $selected)) {
                return $article;
            }
        }
        return null;
    }

    /**
     * @param int[] $sortedSelectedUids
     */
    private function articleMatches(Article $article, array $sortedSelectedUids): bool
    {
        $articleValueUids = [];
        foreach ($article->getAttributeValues() as $value) {
            if ($value->getUid() !== null) {
                $articleValueUids[] = $value->getUid();
            }
        }
        sort($articleValueUids);
        return $articleValueUids === $sortedSelectedUids;
    }
}
