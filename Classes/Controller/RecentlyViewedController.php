<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\RecentlyViewed\ProductViewTrackingService;
use GoldeneZeiten\Products\Service\RecentlyViewed\RecentlyViewedStorage;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class RecentlyViewedController extends ActionController
{
    private const DEFAULT_MOST_VIEWED_LIMIT = 10;

    public function __construct(
        private readonly RecentlyViewedStorage $recentlyViewedStorage,
        private readonly ProductViewTrackingService $productViewTrackingService,
        private readonly ProductRepository $productRepository
    ) {}

    public function listAction(): ResponseInterface
    {
        $this->view->assign('products', $this->resolveProducts());
        return $this->htmlResponse();
    }

    /**
     * Site-wide "most viewed" listing - independent of session/login state.
     */
    public function mostViewedAction(): ResponseInterface
    {
        $this->view->assign('products', $this->productViewTrackingService->getMostViewed($this->mostViewedLimit()));
        return $this->htmlResponse();
    }

    /**
     * Per-user "most viewed" listing (empty for guests).
     */
    public function myMostViewedAction(): ResponseInterface
    {
        $this->view->assign('products', $this->productViewTrackingService->getMostViewedByUser($this->request, $this->mostViewedLimit()));
        return $this->htmlResponse();
    }

    /**
     * @return Product[]
     */
    private function resolveProducts(): array
    {
        $products = [];
        foreach ($this->recentlyViewedStorage->load($this->request) as $productUid) {
            $product = $this->productRepository->findByUid($productUid);
            if ($product instanceof Product) {
                $products[] = $product;
            }
        }
        return $products;
    }

    private function mostViewedLimit(): int
    {
        $limit = $this->settings['mostViewed']['limit'] ?? self::DEFAULT_MOST_VIEWED_LIMIT;
        return max(1, (int)$limit);
    }
}
