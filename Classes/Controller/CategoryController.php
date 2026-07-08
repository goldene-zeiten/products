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
     * Renders the full tree regardless of the current page - "which categories exist" is
     * static browsing chrome, not something that depends on route-resolved arguments.
     */
    public function navigationAction(): ResponseInterface
    {
        $this->view->assign('tree', $this->categoryTreeService->getTree());
        return $this->htmlResponse();
    }

    /**
     * The route enhancer resolves whichever segment is deepest in the matched pattern to
     * $category, independently validating each shallower segment along the way exists as a real
     * category (via its own PersistedAliasMapper aspect) - but not that those segments actually
     * nest under one another. assertRequestPathMatchesCategory() closes that gap by comparing the
     * request's own path suffix against the category's real, precomputed nested path instead of
     * trusting the combination the router happened to match.
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
