<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\RecentlyViewed\RecentlyViewedStorage;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class RecentlyViewedController extends ActionController
{
    public function __construct(
        private readonly RecentlyViewedStorage $recentlyViewedStorage,
        private readonly ProductRepository $productRepository
    ) {}

    public function listAction(): ResponseInterface
    {
        $this->view->assign('products', $this->resolveProducts());
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
}
