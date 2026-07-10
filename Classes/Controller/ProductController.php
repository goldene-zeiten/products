<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Model\Article;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\PageTitle\CurrentProductHolder;
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
        private readonly ArticleVariantResolver $articleVariantResolver,
        private readonly CurrentProductHolder $currentProductHolder
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
     * @param int[] $attributeValues Selected variant attribute-value uids, used when the product
     * has variant attributes; ignored (selectedArticle wins) for the flat article-select fallback.
     * Either path shows that article's own price/stock instead of the product's. The reload
     * (default) and AJAX (opt-in) variant switch modes both resubmit to this very action - AJAX
     * mode just fetches this same URL and extracts the swappable fragment client-side instead of
     * navigating, so there is no second action/argument-namespace to keep in sync with the form.
     */
    public function showAction(Product $product, ?Article $selectedArticle = null, array $attributeValues = []): ResponseInterface
    {
        $this->currentProductHolder->setProduct($product);
        if ($product->getUid() !== null) {
            $this->recentlyViewedStorage->record($this->request, $product->getUid());
        }
        $this->view->assignMultiple([
            'product' => $product,
            'variantAttributes' => $product->getVariantAttributes(),
            'selectedArticle' => $selectedArticle ?? $this->articleVariantResolver->resolve($product, array_map('intval', $attributeValues)),
        ] + $this->wishlistViewVariables());
        return $this->htmlResponse();
    }

    /**
     * @return array{wishlistEnabled: bool, wishlistProductUids: int[], wishlistCount: int}
     */
    private function wishlistViewVariables(): array
    {
        $enabled = $this->wishlistService->isEnabled();
        $productUids = $enabled
            ? array_map(static fn(Product $product): int => $product->getUid() ?? 0, $this->wishlistService->getItems($this->request))
            : [];
        return ['wishlistEnabled' => $enabled, 'wishlistProductUids' => $productUids, 'wishlistCount' => count($productUids)];
    }
}
