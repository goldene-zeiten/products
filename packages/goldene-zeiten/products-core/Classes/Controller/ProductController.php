<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Catalog\ProductListService;
use GoldeneZeiten\Products\Core\Controller\Exception\ProductNotVisibleException;
use GoldeneZeiten\Products\Core\Controller\Exception\ProductPathMismatchException;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListCriteria;
use GoldeneZeiten\Products\Core\Domain\Dto\Catalog\ProductListSettings;
use GoldeneZeiten\Products\Core\Domain\Model\Article;
use GoldeneZeiten\Products\Core\Domain\Model\Category;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Event\EnrichProductViewEvent;
use GoldeneZeiten\Products\Core\Event\ProductViewedEvent;
use GoldeneZeiten\Products\Core\PageTitle\CurrentProductHolder;
use GoldeneZeiten\Products\Core\Seo\ProductHeadRenderer;
use GoldeneZeiten\Products\Core\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Core\Service\ContentElement\RecordsFieldResolver;
use GoldeneZeiten\Products\Core\Service\ContentElement\SelectedCategoriesResolver;
use GoldeneZeiten\Products\Core\Service\Variant\ArticleVariantResolver;
use GoldeneZeiten\Products\Core\Visibility\ProductVisibilityResolver;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class ProductController extends ActionController
{
    private const DEFAULT_NEW_ITEM_DAYS = 7;

    public function __construct(
        private readonly ArticleVariantResolver $articleVariantResolver,
        private readonly CurrentProductHolder $currentProductHolder,
        private readonly CategoryTreeService $categoryTreeService,
        private readonly RecordsFieldResolver $recordsFieldResolver,
        private readonly SelectedCategoriesResolver $selectedCategoriesResolver,
        private readonly ProductVisibilityResolver $productVisibilityResolver,
        private readonly ProductListService $productListService,
        private readonly ProductHeadRenderer $productHeadRenderer,
    ) {}

    public function listAction(int $currentPage = 1): ResponseInterface
    {
        $this->view->assignMultiple($this->listVariables($currentPage) + $this->enrichViewVariables());
        return $this->htmlResponse();
    }

    public function listByAjaxAction(int $currentPage = 1): ResponseInterface
    {
        $this->view->assignMultiple($this->listVariables($currentPage) + $this->enrichViewVariables());
        return $this->htmlResponse();
    }

    /**
     * @param int[] $attributeValues Selected variant attribute-value uids (ignored if $selectedArticle is set).
     * @throws ProductPathMismatchException
     * @throws ProductNotVisibleException
     */
    public function showAction(
        ?Product $product = null,
        ?Category $category = null,
        ?Article $selectedArticle = null,
        array $attributeValues = []
    ): ResponseInterface {
        $product ??= $selectedArticle?->getProduct();
        if (!$product instanceof Product) {
            throw new ProductPathMismatchException(
                'Neither a product nor a selected article could be resolved from the request.',
                1783800100
            );
        }
        if ($selectedArticle?->getProduct()?->getUid() !== $product->getUid()) {
            $selectedArticle = null;
        }
        if ($category instanceof Category) {
            $this->assertRequestPathMatchesProduct($product, $category, $selectedArticle);
        }

        if (!$this->productVisibilityResolver->isVisible($product, $this->request)) {
            throw new ProductNotVisibleException(
                sprintf('Product %d is not visible to the current visitor.', (int)$product->getUid()),
                1783800103
            );
        }

        $this->currentProductHolder->setProduct($product);
        $selectedArticle ??= $this->articleVariantResolver->resolve($product, array_map('intval', $attributeValues));
        $this->productHeadRenderer->render($product, $selectedArticle, $this->request);
        $this->eventDispatcher->dispatch(new ProductViewedEvent($product, $this->request));
        $this->view->assignMultiple([
            'product' => $product,
            'variantAttributes' => $product->getVariantAttributes(),
            'relatedProducts' => $this->productVisibilityResolver->filterVisible($product->getRelatedProducts()->toArray(), $this->request),
            'accessoryProducts' => $this->productVisibilityResolver->filterVisible($product->getAccessoryProducts()->toArray(), $this->request),
            'selectedArticle' => $selectedArticle,
        ] + $this->enrichViewVariables());
        return $this->htmlResponse();
    }

    /**
     * Only called when routing actually resolved a category segment - a flat, category-less
     * request never reaches this check, exactly like CategoryController only validates when a
     * category was resolved.
     *
     * @throws ProductPathMismatchException
     */
    private function assertRequestPathMatchesProduct(Product $product, Category $category, ?Article $selectedArticle): void
    {
        if (!$product->getCategories()->contains($category)) {
            throw new ProductPathMismatchException(
                sprintf('Product %d is not assigned to category %d.', (int)$product->getUid(), (int)$category->getUid()),
                1783800101
            );
        }
        $requestedPath = trim($this->request->getUri()->getPath(), '/');
        $lastSegment = $selectedArticle instanceof Article ? $selectedArticle->getSlug() : $product->getSlug();
        $expectedSuffix = $this->categoryTreeService->resolveSlugPath($category) . '/' . $this->categoryTreeService->ownSlugSegment($lastSegment);
        if (!str_ends_with($requestedPath, $expectedSuffix)) {
            throw new ProductPathMismatchException(
                sprintf('Requested path "%s" does not match the actual nesting for product %d.', $requestedPath, (int)$product->getUid()),
                1783800102
            );
        }
    }

    /**
     * Visibility rules apply to the products of the current page; a hidden product leaves its place on
     * that page empty rather than pulling the next one in.
     *
     * @return array<string, mixed>
     */
    private function listVariables(int $currentPage): array
    {
        $contentElement = $this->request->getAttribute('currentContentObject')->data ?? [];
        $settings = ProductListSettings::fromContentElement($contentElement);
        $paginator = $this->productListService->paginate($this->request, $this->listCriteria($contentElement, $settings), $settings, $currentPage);
        return [
            'products' => $this->productVisibilityResolver->filterVisible(iterator_to_array($paginator->getPaginatedItems(), false), $this->request),
            'paginator' => $paginator,
            'pagination' => $this->productListService->pagination($paginator, $this->site()),
            'showPagination' => $settings->pagination && $settings->isPaged() && $paginator->getNumberOfPages() > 1,
        ];
    }

    /**
     * @param array<string, mixed> $contentElement
     */
    private function listCriteria(array $contentElement, ProductListSettings $settings): ProductListCriteria
    {
        $categoryUids = [];
        foreach ($this->selectedCategoriesResolver->resolveUids($this->request) as $categoryUid) {
            $categoryUids = array_merge($categoryUids, $this->categoryTreeService->getSubtreeUids($categoryUid));
        }
        return new ProductListCriteria(
            (string)($contentElement['tx_products_list_mode'] ?? 'all'),
            $this->recordsFieldResolver->resolveUids($this->request, 'tx_products_domain_model_product'),
            array_values(array_unique($categoryUids)),
            $settings->sorting,
            $this->getNewItemDays(),
        );
    }

    private function site(): ?Site
    {
        $site = $this->request->getAttribute('site');
        return $site instanceof Site ? $site : null;
    }

    private function getNewItemDays(): int
    {
        $site = $this->request->getAttribute('site');
        if ($site !== null) {
            $days = $site->getSettings()->get('products.list.newItemDays', self::DEFAULT_NEW_ITEM_DAYS);
            return max(1, (int)$days);
        }
        return self::DEFAULT_NEW_ITEM_DAYS;
    }

    /**
     * Variables add-ons contribute to the list and detail views (e.g. wishlist state), collected through an
     * event so the core catalog controller does not depend on any of those features.
     *
     * @return array<string, mixed>
     */
    private function enrichViewVariables(): array
    {
        $event = new EnrichProductViewEvent($this->request);
        $this->eventDispatcher->dispatch($event);
        return $event->getVariables();
    }
}
