<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Controller;

use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Security\FrontendRequestTokenValidator;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Variant\ArticleVariantResolver;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

final class BasketController extends ActionController
{
    private const REQUEST_TOKEN_SCOPE = 'products/basket';

    public function __construct(
        private readonly BasketService $basketService,
        private readonly ProductRepository $productRepository,
        private readonly ArticleVariantResolver $articleVariantResolver,
        private readonly FrontendRequestTokenValidator $requestTokenValidator
    ) {}

    /**
     * A state-changing basket action is only honoured when it carries the request token the basket forms
     * issue; a cross-site POST that cannot present it is bounced back to the basket without mutating it.
     */
    private function rejectForgedRequest(): ?ResponseInterface
    {
        if ($this->requestTokenValidator->isValid(self::REQUEST_TOKEN_SCOPE)) {
            return null;
        }
        $this->addFlashMessage(
            (string)LocalizationUtility::translate('csrf.invalid_request', 'ProductsCore'),
            '',
            ContextualFeedbackSeverity::ERROR
        );

        return $this->redirect('show');
    }

    public function showAction(): ResponseInterface
    {
        $this->view->assign('basket', $this->basketService->getBasketViewModel($this->request));
        return $this->htmlResponse();
    }

    /**
     * @param int[] $attributeValues Selected variant attribute-value uids (ignored if $article is set).
     */
    public function addAction(int $product, ?int $article = null, int $quantity = 1, array $attributeValues = []): ResponseInterface
    {
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
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
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
        $this->basketService->update($this->request, $product, $article, $quantity);
        return $this->redirect('show');
    }

    public function removeAction(int $product, ?int $article = null): ResponseInterface
    {
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
        $this->basketService->remove($this->request, $product, $article);
        return $this->redirect('show');
    }
}
