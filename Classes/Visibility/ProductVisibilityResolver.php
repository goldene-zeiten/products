<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Visibility;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

/**
 * Aggregates all registered product visibility checkers and enforces deny-wins-over-allow semantics.
 */
final class ProductVisibilityResolver
{
    /**
     * @param iterable<ProductVisibilityInterface> $checkers
     */
    public function __construct(
        #[TaggedIterator('products.product_visibility')]
        private readonly iterable $checkers
    ) {}

    /**
     * Check if a product is visible to the current visitor.
     *
     * Returns false if any checker denies access; true only if all checkers allow.
     */
    public function isVisible(Product $product, ServerRequestInterface $request): bool
    {
        foreach ($this->checkers as $checker) {
            if (!$checker->isVisible($product, $request)) {
                return false;
            }
        }
        return true;
    }

    /**
     * Filter products by visibility.
     *
     * @param Product[] $products
     * @return Product[]
     */
    public function filterVisible(array $products, ServerRequestInterface $request): array
    {
        return array_values(array_filter(
            $products,
            fn(Product $product): bool => $this->isVisible($product, $request)
        ));
    }
}
