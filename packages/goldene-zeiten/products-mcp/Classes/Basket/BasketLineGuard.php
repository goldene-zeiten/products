<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Basket;

use GoldeneZeiten\Products\Core\Domain\Dto\Basket;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Service\Basket\BasketQuantityResolver;
use GoldeneZeiten\Products\Mcp\Exception\ToolFailedException;

/**
 * Refuses what the shop would not sell, with a reason the assistant can pass on: a product with variants
 * needs one of its own, and the quantity has to be within the basket limits of the product or variant and
 * within the stock. The frontend basket only checks the stock when the order is placed; an assistant is told
 * at once.
 */
final readonly class BasketLineGuard
{
    public function __construct(
        private BasketQuantityResolver $quantityResolver
    ) {}

    /**
     * @throws ToolFailedException when a variant is missing, or is none of the product
     */
    public function article(Product $product, ?int $articleUid): ?Article
    {
        $articles = $product->getArticles();
        if ($articleUid === null) {
            if ($articles->count() > 0) {
                throw new ToolFailedException(
                    sprintf('The product %d is sold in variants; name the variant as "article".', (int)$product->getUid()),
                    1792711030
                );
            }

            return null;
        }
        foreach ($articles as $article) {
            if ($article->getUid() === $articleUid) {
                return $article;
            }
        }

        throw new ToolFailedException(
            sprintf('The article %d is not a variant of the product %d.', $articleUid, (int)$product->getUid()),
            1792711031
        );
    }

    /**
     * How many of a product or variant the basket holds across all its lines, whatever their options.
     */
    public function heldQuantity(Basket $basket, int $productUid, ?int $articleUid): int
    {
        $held = 0;
        foreach ($basket->getItems() as $item) {
            if ($item->getProductUid() === $productUid && $item->getArticleUid() === $articleUid) {
                $held += $item->getQuantity();
            }
        }

        return $held;
    }

    /**
     * @param int $quantity What the basket would hold of the product or variant
     * @throws ToolFailedException
     */
    public function assertQuantity(Product $product, ?Article $article, int $quantity): void
    {
        $min = $this->quantityResolver->resolveMinQuantity($product, $article);
        $max = $this->quantityResolver->resolveMaxQuantity($product, $article);
        if ($min > 0 && $quantity < $min) {
            throw new ToolFailedException(sprintf('At least %d have to be bought at once; asked for %d.', $min, $quantity), 1792711032);
        }
        if ($max > 0 && $quantity > $max) {
            throw new ToolFailedException(sprintf('At most %d can be bought at once; asked for %d.', $max, $quantity), 1792711033);
        }
        $stocked = $article ?? $product;
        if (!$stocked->isUnlimitedStock() && $quantity > $stocked->getInStock()) {
            throw new ToolFailedException(
                sprintf('Only %d are in stock; asked for %d in total.', $stocked->getInStock(), $quantity),
                1792711034
            );
        }
    }
}
