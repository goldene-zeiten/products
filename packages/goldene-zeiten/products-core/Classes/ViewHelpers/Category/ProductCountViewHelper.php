<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\ViewHelpers\Category;

use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\ViewHelpers\Format\RenderingContextRequestResolverInterface;
use GoldeneZeiten\Products\Core\Visibility\ProductVisibilityResolver;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * Counts the products of a category the current visitor may see.
 */
final class ProductCountViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductVisibilityResolver $productVisibilityResolver,
        private readonly RenderingContextRequestResolverInterface $requestResolver,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('category', Category::class, 'The category to count direct products for', true);
    }

    public function render(): int
    {
        /** @var Category $category */
        $category = $this->arguments['category'];
        $request = $this->requestResolver->resolveRequest($this->renderingContext);
        if (!$request instanceof ServerRequestInterface) {
            return $this->productRepository->countByCategory($category);
        }
        return count($this->productVisibilityResolver->filterVisible($this->productRepository->findByCategory($category), $request));
    }
}
