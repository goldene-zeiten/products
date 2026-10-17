<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\ViewHelpers\Pricing;

use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\VariantPriceRange;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Pricing\VariantPriceRangeResolver;
use GoldeneZeiten\Products\Core\ViewHelpers\Format\RenderingContextRequestResolverInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * The price range of a product's variants, for a "from" price while no variant is chosen; null for a product
 * without variants.
 *
 * ```
 * <f:variable name="priceRange" value="{p:pricing.variantPriceRange(product: product)}" />
 * ```
 */
final class VariantPriceRangeViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly VariantPriceRangeResolver $priceRangeResolver,
        private readonly RenderingContextRequestResolverInterface $requestResolver,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('product', Product::class, 'The product entity', true);
    }

    public function render(): ?VariantPriceRange
    {
        $product = $this->arguments['product'];
        if (!$product instanceof Product) {
            return null;
        }
        return $this->priceRangeResolver->resolve($product, $this->requestResolver->resolveRequest($this->renderingContext));
    }
}
