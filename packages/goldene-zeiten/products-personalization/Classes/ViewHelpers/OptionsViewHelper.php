<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\ViewHelpers;

use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Personalization\Domain\Model\Option;
use GoldeneZeiten\Products\Personalization\Domain\Repository\OptionRepository;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * The options a customer can choose for a product, or a variant of it, in the add-to-basket form:
 *
 *     <f:for each="{pp:options(product: product, article: article)}" as="option">...</f:for>
 */
final class OptionsViewHelper extends AbstractViewHelper
{
    protected $escapeOutput = false;

    public function __construct(
        private readonly OptionRepository $optionRepository
    ) {}

    public function initializeArguments(): void
    {
        $this->registerArgument('product', Product::class, 'The product added to the basket', true);
        $this->registerArgument('article', Article::class, 'The variant added to the basket');
    }

    /**
     * @return Option[]
     */
    public function render(): array
    {
        $product = $this->arguments['product'];
        $article = $this->arguments['article'];
        if (!$product instanceof Product) {
            return [];
        }
        return array_values($this->optionRepository->findForLine((int)$product->getUid(), $article instanceof Article ? $article->getUid() : null));
    }
}
