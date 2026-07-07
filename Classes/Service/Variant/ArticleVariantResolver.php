<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Variant;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;

/**
 * Matches a selected set of attribute-value uids to the one article carrying exactly that
 * combination. Used as the no-JS fallback path; the JS-enhanced selector resolves the same
 * combination client-side from the page's own variant map instead of round-tripping here.
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
