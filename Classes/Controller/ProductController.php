<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Controller\Exception\ProductNotVisibleException;
use GoldeneZeiten\Products\Controller\Exception\ProductPathMismatchException;
use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ArticleRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Event\ModifyProductListEvent;
use GoldeneZeiten\Products\PageTitle\CurrentProductHolder;
use GoldeneZeiten\Products\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Service\ContentElement\RecordsFieldResolver;
use GoldeneZeiten\Products\Service\ContentElement\SelectedCategoriesResolver;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsBalanceService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\RecentlyViewed\ProductViewTrackingService;
use GoldeneZeiten\Products\Service\RecentlyViewed\RecentlyViewedStorage;
use GoldeneZeiten\Products\Service\Variant\ArticleVariantResolver;
use GoldeneZeiten\Products\Service\Wishlist\WishlistService;
use GoldeneZeiten\Products\Visibility\ProductVisibilityResolver;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class ProductController extends ActionController
{
    private const DEFAULT_NEW_ITEM_DAYS = 7;

    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ArticleRepository $articleRepository,
        private readonly WishlistService $wishlistService,
        private readonly RecentlyViewedStorage $recentlyViewedStorage,
        private readonly ProductViewTrackingService $productViewTrackingService,
        private readonly ArticleVariantResolver $articleVariantResolver,
        private readonly CurrentProductHolder $currentProductHolder,
        private readonly CategoryTreeService $categoryTreeService,
        private readonly RecordsFieldResolver $recordsFieldResolver,
        private readonly SelectedCategoriesResolver $selectedCategoriesResolver,
        private readonly CreditPointsBalanceService $creditPointsBalanceService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly ProductVisibilityResolver $productVisibilityResolver
    ) {}

    public function listAction(): ResponseInterface
    {
        $products = $this->resolveProductsForList();
        $this->view->assignMultiple(['products' => $products] + $this->wishlistViewVariables());
        return $this->htmlResponse();
    }

    public function listByAjaxAction(): ResponseInterface
    {
        $products = $this->resolveProductsForList();
        $this->view->assignMultiple(['products' => $products] + $this->wishlistViewVariables());
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
        if ($product->getUid() !== null) {
            $this->recentlyViewedStorage->record($this->request, $product->getUid());
            $this->productViewTrackingService->record($this->request, $product->getUid());
        }
        $this->view->assignMultiple([
            'product' => $product,
            'variantAttributes' => $product->getVariantAttributes(),
            'selectedArticle' => $selectedArticle ?? $this->articleVariantResolver->resolve($product, array_map('intval', $attributeValues)),
        ] + $this->wishlistViewVariables());
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
     * @return Product[]
     */
    private function resolveProductsForList(): array
    {
        $mode = (string)($this->request->getAttribute('currentContentObject')?->data['tx_products_list_mode'] ?? 'all');
        $products = $this->resolveProducts($mode);
        $selectedProductUids = $this->recordsFieldResolver->resolveUids($this->request, 'tx_products_domain_model_product');
        if ($selectedProductUids !== []) {
            $products = array_filter(
                $products,
                static fn(Product $product): bool => in_array($product->getUid() ?? 0, $selectedProductUids, true)
            );
        }
        $selectedCategoryUids = $this->selectedCategoriesResolver->resolveUids($this->request);
        if ($selectedCategoryUids !== []) {
            $categorySubtreeUids = [];
            foreach ($selectedCategoryUids as $categoryUid) {
                $categorySubtreeUids = array_merge($categorySubtreeUids, $this->categoryTreeService->getSubtreeUids($categoryUid));
            }
            $categorySubtreeUids = array_unique($categorySubtreeUids);
            $products = array_filter(
                $products,
                static function (Product $product) use ($categorySubtreeUids): bool {
                    foreach ($product->getCategories() as $category) {
                        if (in_array($category->getUid() ?? 0, $categorySubtreeUids, true)) {
                            return true;
                        }
                    }
                    return false;
                }
            );
        }
        $event = new ModifyProductListEvent($products, $this->request);
        $this->eventDispatcher->dispatch($event);
        return $this->productVisibilityResolver->filterVisible($event->getProducts(), $this->request);
    }

    /**
     * @return Product[]
     */
    private function resolveProducts(string $mode): array
    {
        $result = match ($mode) {
            'offers' => $this->productRepository->findOffers(),
            'highlights' => $this->productRepository->findHighlights(),
            'new' => $this->productRepository->findNew($this->getNewItemDays()),
            'affordable' => $this->productRepository->findAffordable($this->getCreditPointsBalance()),
            'articles' => $this->resolveProductsFromArticles(),
            default => $this->productRepository->findAll(),
        };
        return is_array($result) ? $result : $result->toArray();
    }

    /**
     * @return Product[]
     */
    private function resolveProductsFromArticles(): array
    {
        $articles = $this->articleRepository->findAllFlat();
        $productMap = [];
        foreach ($articles as $article) {
            $product = $article->getProduct();
            if ($product instanceof Product) {
                $uid = $product->getUid();
                if ($uid !== null && !isset($productMap[$uid])) {
                    $productMap[$uid] = $product;
                }
            }
        }
        return array_values($productMap);
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

    private function getCreditPointsBalance(): int
    {
        $uid = $this->frontendUserResolver->getUid($this->request);
        return $this->creditPointsBalanceService->getBalance($uid);
    }

    /**
     * @return array{wishlistEnabled: bool, wishlistProductUids: int[], wishlistCount: int}
     */
    private function wishlistViewVariables(): array
    {
        $enabled = $this->wishlistService->isEnabled($this->request);
        $productUids = $enabled
            ? array_map(static fn(Product $product): int => $product->getUid() ?? 0, $this->wishlistService->getItems($this->request))
            : [];
        return ['wishlistEnabled' => $enabled, 'wishlistProductUids' => $productUids, 'wishlistCount' => count($productUids)];
    }
}
