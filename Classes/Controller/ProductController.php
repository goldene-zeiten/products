<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Controller\Exception\ProductPathMismatchException;
use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Category;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\PageTitle\CurrentProductHolder;
use GoldeneZeiten\Products\Service\Category\CategoryTreeService;
use GoldeneZeiten\Products\Service\RecentlyViewed\ProductViewTrackingService;
use GoldeneZeiten\Products\Service\RecentlyViewed\RecentlyViewedStorage;
use GoldeneZeiten\Products\Service\Variant\ArticleVariantResolver;
use GoldeneZeiten\Products\Service\Wishlist\WishlistService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class ProductController extends ActionController
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly WishlistService $wishlistService,
        private readonly RecentlyViewedStorage $recentlyViewedStorage,
        private readonly ProductViewTrackingService $productViewTrackingService,
        private readonly ArticleVariantResolver $articleVariantResolver,
        private readonly CurrentProductHolder $currentProductHolder,
        private readonly CategoryTreeService $categoryTreeService
    ) {}

    public function listAction(): ResponseInterface
    {
        $products = $this->productRepository->findAll();
        $this->view->assignMultiple(['products' => $products] + $this->wishlistViewVariables());
        return $this->htmlResponse();
    }

    public function listByAjaxAction(): ResponseInterface
    {
        $products = $this->productRepository->findAll();
        $this->view->assignMultiple(['products' => $products] + $this->wishlistViewVariables());
        return $this->htmlResponse();
    }

    /**
     * @param int[] $attributeValues Selected variant attribute-value uids (ignored if $selectedArticle is set).
     * @throws ProductPathMismatchException
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
