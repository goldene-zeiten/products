<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Pricing;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Pricing\PriceProviderInterface;
use GoldeneZeiten\Products\ViewHelpers\Format\RenderingContextRequestResolverInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Resolves the same period/tier/category-discount-adjusted unit price
 * {@see \GoldeneZeiten\Products\Service\Basket\BasketService} charges at checkout, so product
 * listing/detail pages show the price a shopper will actually pay rather than the raw stored
 * base price.
 */
final class DisplayPriceViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly PriceProviderInterface $priceProvider,
        private readonly RenderingContextRequestResolverInterface $requestResolver,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('product', 'mixed', 'The product entity', true);
        $this->registerArgument('article', 'mixed', 'The selected article entity, if any', false, null);
        $this->registerArgument('quantity', 'int', 'Quantity to resolve tier pricing for', false, 1);
    }

    public function render(): Money
    {
        $product = $this->arguments['product'];
        if (!$product instanceof Product) {
            throw new \InvalidArgumentException('DisplayPriceViewHelper requires a Product entity in "product".', 1783950000);
        }
        $article = $this->arguments['article'];

        return $this->priceProvider->getUnitPrice(
            $product,
            $article instanceof Article ? $article : null,
            (int)$this->arguments['quantity'],
            $this->requestResolver->resolveRequest($this->renderingContext)
        );
    }
}
