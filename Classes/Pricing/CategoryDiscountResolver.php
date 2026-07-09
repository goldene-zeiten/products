<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Pricing;

use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;

/**
 * Category-cascading flat-percentage discount, mirroring legacy tt_products' `discount`/
 * `discount_disable` fields and `discountFieldMode`: mode "maxAcrossTree" matches legacy mode 1's
 * `getMaxDiscount()` (highest discount found anywhere across every assigned category's full
 * ancestor chain, ignoring each category's own `discountDisabled` flag entirely); mode
 * "nearestCategory" matches legacy mode 2's `getFirstDiscount()` (walks from the product's own
 * category up to the root, stopping at - and zeroing the result for - the first disabled category,
 * or returning the first positive discount found). A product's own `discountDisabled`
 * unconditionally zeroes the result in both modes, matching legacy's shared `bDiscountDisable` gate.
 */
final class CategoryDiscountResolver
{
    public function getDiscountPercent(Product $product, string $mode): float
    {
        if ($product->isDiscountDisabled()) {
            return 0.0;
        }

        return $mode === 'nearestCategory'
            ? $this->nearestCategoryDiscount($product)
            : $this->maxAcrossTreeDiscount($product);
    }

    private function maxAcrossTreeDiscount(Product $product): float
    {
        $max = $product->getDiscountPercent();
        foreach ($product->getCategories() as $category) {
            foreach ($this->ancestorChain($category) as $ancestor) {
                $max = max($max, $ancestor->getDiscountPercent());
            }
        }
        return $max;
    }

    private function nearestCategoryDiscount(Product $product): float
    {
        if ($product->getDiscountPercent() > 0.0) {
            return $product->getDiscountPercent();
        }

        $best = 0.0;
        foreach ($product->getCategories() as $category) {
            $nearestFirst = array_reverse($this->ancestorChain($category));
            $best = max($best, $this->walkNearest($nearestFirst));
        }
        return $best;
    }

    /**
     * @param Category[] $nearestFirstChain
     */
    private function walkNearest(array $nearestFirstChain): float
    {
        foreach ($nearestFirstChain as $category) {
            if ($category->isDiscountDisabled()) {
                return 0.0;
            }
            if ($category->getDiscountPercent() > 0.0) {
                return $category->getDiscountPercent();
            }
        }
        return 0.0;
    }

    /**
     * Root-first, including $category itself - same shape as
     * CategoryTreeService::getAncestorChain(), reimplemented here (rather than injecting that
     * service) so this resolver stays a pure, DI-free unit under test, matching
     * GraduatedPriceProvider's testability.
     *
     * @return Category[]
     */
    private function ancestorChain(Category $category): array
    {
        $chain = [$category];
        $parent = $category->getParentCategory();
        while ($parent instanceof Category) {
            array_unshift($chain, $parent);
            $parent = $parent->getParentCategory();
        }
        return $chain;
    }
}
