<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Service\Basket;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;

/**
 * Resolves basket quantity bounds: article min/max overrides product when set; 0 = unlimited.
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
     * Clamp quantity to [min, max] bounds (0 = unlimited).
     */
    public function clamp(Product $product, ?Article $article, int $quantity): int
    {
        $min = $this->resolveMinQuantity($product, $article);
        $max = $this->resolveMaxQuantity($product, $article);

        $clamped = $min > 0 ? max($quantity, $min) : $quantity;
        return $max > 0 ? min($clamped, $max) : $clamped;
    }
}
