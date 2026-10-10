<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Pricing;

use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Product;

/**
 * Category-cascading flat-percentage discount. "maxAcrossTree" takes the highest discount across
 * the full ancestor chain; "nearestCategory" walks up and stops at the first disabled category.
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
            foreach ($category->getAncestorChain() as $ancestor) {
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
            $nearestFirst = array_reverse($category->getAncestorChain());
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
}
