<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Controller\Exception\CategoryPathMismatchException;
use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\Category\CategoryTreeService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class CategoryController extends ActionController
{
    public function __construct(
        private readonly CategoryTreeService $categoryTreeService,
        private readonly ProductRepository $productRepository
    ) {}

    /**
     * Renders the full category tree (independent of page context).
     */
    public function navigationAction(): ResponseInterface
    {
        $this->view->assign('tree', $this->categoryTreeService->getTree());
        return $this->htmlResponse();
    }

    /**
     * Validates that the request path actually matches the category's nested ancestry.
     *
     * @throws CategoryPathMismatchException
     */
    public function listAction(?Category $category = null): ResponseInterface
    {
        if ($category instanceof Category) {
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
