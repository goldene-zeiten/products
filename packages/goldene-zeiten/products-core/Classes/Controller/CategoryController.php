<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Core\Service\ContentElement\SelectedCategoriesResolver;
use GoldeneZeiten\Products\Core\Visibility\ProductVisibilityResolver;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class CategoryController extends ActionController
{
    public function __construct(
        private readonly CategoryTreeService $categoryTreeService,
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly SelectedCategoriesResolver $selectedCategoriesResolver,
        private readonly ProductVisibilityResolver $productVisibilityResolver
    ) {}

    /**
     * Renders the full category tree (independent of page context).
     */
    public function navigationAction(): ResponseInterface
    {
        $navigationStyle = (string)($this->request->getAttribute('currentContentObject')?->data['tx_products_navigation_style'] ?? 'menu');
        $this->view->assignMultiple([
            'tree' => $this->categoryTreeService->getTree(),
            'navigationStyle' => $navigationStyle,
        ]);
        return $this->htmlResponse();
    }

    /**
     * Lists the routed category, or the first category selected in the content element.
     */
    public function listAction(?Category $category = null): ResponseInterface
    {
        if ($category === null) {
            $selectedCategoryUids = $this->selectedCategoriesResolver->resolveUids($this->request);
            if ($selectedCategoryUids !== []) {
                $category = $this->categoryRepository->findByUidIgnoringStoragePage($selectedCategoryUids[0]);
            }
        }
        $this->view->assignMultiple([
            'category' => $category,
            'ancestorChain' => $category instanceof Category ? $this->categoryTreeService->getAncestorChain($category) : [],
            'products' => $category instanceof Category
                ? $this->productVisibilityResolver->filterVisible($this->productRepository->findByCategory($category), $this->request)
                : [],
        ]);
        return $this->htmlResponse();
    }
}
