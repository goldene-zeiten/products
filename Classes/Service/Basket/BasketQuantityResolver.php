<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Basket;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;

/**
 * Resolves the effective basket-quantity bounds for a product/article pair - an article's own
 * min/max overrides the product's whenever it is set (non-zero), mirroring legacy tt_products'
 * per-article override precedence; 0 means "no limit" at either level.
 */
final class BasketQuantityResolver
{
    public function resolveMinQuantity(Product $product, ?Article $article): int
    {
        if ($article instanceof Article && $article->getBasketMinQuantity() > 0) {
            return $article->getBasketMinQuantity();
        }
        return $product->getBasketMinQuantity();
    }

    public function resolveMaxQuantity(Product $product, ?Article $article): int
    {
        if ($article instanceof Article && $article->getBasketMaxQuantity() > 0) {
            return $article->getBasketMaxQuantity();
        }
        return $product->getBasketMaxQuantity();
    }

    /**
     * Clamps a requested quantity into [min, max] (0 on either side = no bound), matching
     * legacy's MathUtility::forceIntegerInRange() clamping behaviour rather than rejecting the
     * request outright.
     */
    public function clamp(Product $product, ?Article $article, int $quantity): int
    {
        $min = $this->resolveMinQuantity($product, $article);
        $max = $this->resolveMaxQuantity($product, $article);

        $clamped = $min > 0 ? max($quantity, $min) : $quantity;
        return $max > 0 ? min($clamped, $max) : $clamped;
    }
}
