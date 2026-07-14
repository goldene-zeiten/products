<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Controller\Exception\CategoryPathMismatchException;
use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Core\Service\ContentElement\SelectedCategoriesResolver;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class CategoryController extends ActionController
{
    public function __construct(
        private readonly CategoryTreeService $categoryTreeService,
        private readonly ProductRepository $productRepository,
        private readonly CategoryRepository $categoryRepository,
        private readonly SelectedCategoriesResolver $selectedCategoriesResolver
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
     * Validates that the request path actually matches the category's nested ancestry.
     *
     * @throws CategoryPathMismatchException
     */
    public function listAction(?Category $category = null): ResponseInterface
    {
        if ($category === null) {
            $selectedCategoryUids = $this->selectedCategoriesResolver->resolveUids($this->request);
            if ($selectedCategoryUids !== []) {
                $category = $this->categoryRepository->findByUidIgnoringStoragePage($selectedCategoryUids[0]);
            }
        } else {
            $this->assertRequestPathMatchesCategory($category);
        }
        $this->view->assignMultiple([
            'category' => $category,
            'ancestorChain' => $category instanceof Category ? $this->categoryTreeService->getAncestorChain($category) : [],
            'products' => $category instanceof Category ? $this->productRepository->findByCategory($category) : [],
        ]);
        return $this->htmlResponse();
    }

    /**
     * @throws CategoryPathMismatchException
     */
    private function assertRequestPathMatchesCategory(Category $category): void
    {
        $requestedPath = trim($this->request->getUri()->getPath(), '/');
        $expectedSuffix = $this->categoryTreeService->resolveSlugPath($category);
        if (!str_ends_with($requestedPath, $expectedSuffix)) {
            throw new CategoryPathMismatchException(
                sprintf('Requested path "%s" does not match the actual nesting of category %d.', $requestedPath, (int)$category->getUid()),
                1783800000
            );
        }
    }
}
