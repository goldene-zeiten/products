<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Category;

use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

final class ProductCountViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly ProductRepository $productRepository,
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('category', Category::class, 'The category to count direct products for', true);
    }

    public function render(): int
    {
        /** @var Category $category */
        $category = $this->arguments['category'];
        return $this->productRepository->countByCategory($category);
    }
}
