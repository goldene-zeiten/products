<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Catalog\ProductListService;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListCriteria;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListSettings;
use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Repository\CategoryRepository;
use GoldeneZeiten\Products\Core\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Core\Service\ContentElement\SelectedCategoriesResolver;
use GoldeneZeiten\Products\Core\Visibility\ProductVisibilityResolver;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class CategoryController extends ActionController
{
    public function __construct(
        private readonly CategoryTreeService $categoryTreeService,
        private readonly ProductListService $productListService,
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
    public function listAction(?Category $category = null, int $currentPage = 1): ResponseInterface
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
        ] + $this->productVariables($category, $currentPage));
        return $this->htmlResponse();
    }

    /**
     * The category's direct products, paged as the content element configures it.
     *
     * @return array<string, mixed>
     */
    private function productVariables(?Category $category, int $currentPage): array
    {
        if (!$category instanceof Category) {
            return ['products' => []];
        }
        $settings = ProductListSettings::fromContentElement($this->request->getAttribute('currentContentObject')->data ?? []);
        $criteria = new ProductListCriteria(categoryUids: [(int)$category->getUid()], sorting: $settings->sorting, respectStoragePage: false);
        $paginator = $this->productListService->paginate($this->request, $criteria, $settings, $currentPage);
        $site = $this->request->getAttribute('site');
        return [
            'products' => $this->productVisibilityResolver->filterVisible(iterator_to_array($paginator->getPaginatedItems(), false), $this->request),
            'paginator' => $paginator,
            'pagination' => $this->productListService->pagination($paginator, $site instanceof Site ? $site : null),
            'showPagination' => $settings->pagination && $settings->isPaged() && $paginator->getNumberOfPages() > 1,
        ];
    }
}
