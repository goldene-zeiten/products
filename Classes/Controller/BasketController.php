<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\Variant\ArticleVariantResolver;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class BasketController extends ActionController
{
    public function __construct(
        private readonly BasketService $basketService,
        private readonly ProductRepository $productRepository,
        private readonly ArticleVariantResolver $articleVariantResolver
    ) {}

    public function showAction(): ResponseInterface
    {
        $basketViewModel = $this->basketService->getBasketViewModel($this->request);
        $this->view->assign('basket', $basketViewModel);
        return $this->htmlResponse();
    }

    /**
     * @param int[] $attributeValues Selected variant attribute-value uids, used only when
     *   $article is not already resolved (the no-JS fallback path of the variant selector).
     */
    public function addAction(int $product, ?int $article = null, int $quantity = 1, array $attributeValues = []): ResponseInterface
    {
        $article ??= $this->resolveArticleByAttributeValues($product, $attributeValues);
        $this->basketService->add($this->request, $product, $article, $quantity);
        return $this->redirect('show');
    }

    /**
     * @param int[] $attributeValues
     */
    private function resolveArticleByAttributeValues(int $productUid, array $attributeValues): ?int
    {
        if ($attributeValues === []) {
            return null;
        }
        $productEntity = $this->productRepository->findByUid($productUid);
        if (!$productEntity instanceof Product) {
            return null;
        }
        $resolvedArticle = $this->articleVariantResolver->resolve($productEntity, array_map('intval', $attributeValues));
        return $resolvedArticle?->getUid();
    }

    public function updateAction(int $product, ?int $article = null, int $quantity = 1): ResponseInterface
    {
        $this->basketService->update($this->request, $product, $article, $quantity);
        return $this->redirect('show');
    }

    public function removeAction(int $product, ?int $article = null): ResponseInterface
    {
        $this->basketService->remove($this->request, $product, $article);
        return $this->redirect('show');
    }
}
