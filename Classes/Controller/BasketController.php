<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Controller;

use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Model\Voucher;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Variant\ArticleVariantResolver;
use GoldeneZeiten\Products\Service\Voucher\Exception\VoucherExceptionInterface;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

final class BasketController extends ActionController
{
    public function __construct(
        private readonly BasketService $basketService,
        private readonly ProductRepository $productRepository,
        private readonly ArticleVariantResolver $articleVariantResolver,
        private readonly VoucherService $voucherService,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function showAction(): ResponseInterface
    {
        $basketViewModel = $this->basketService->getBasketViewModel($this->request);
        $discountSummary = $this->voucherService->buildDiscountSummary(
            $this->basketService->getAppliedVoucherCodes($this->request),
            $basketViewModel->getTotalGross(),
            $this->frontendUserResolver->getUid($this->request)
        );
        $this->view->assignMultiple([
            'basket' => $basketViewModel,
            'discountSummary' => $discountSummary,
            'finalTotal' => $basketViewModel->getTotalGross()->subtract($discountSummary->getDiscountTotal()),
        ]);
        return $this->htmlResponse();
    }

    public function applyVoucherAction(string $voucherCode): ResponseInterface
    {
        $basketViewModel = $this->basketService->getBasketViewModel($this->request);
        $frontendUser = $this->frontendUserResolver->getUid($this->request);

        try {
            $newVoucher = $this->voucherService->resolve(
                $voucherCode,
                $basketViewModel->getTotalGross(),
                $frontendUser,
                $this->basketService->isAlreadyDiscounted($this->request)
            );
        } catch (VoucherExceptionInterface $exception) {
            $this->addFlashMessage($exception->getMessage(), '', ContextualFeedbackSeverity::ERROR);
            return $this->redirect('show');
        }

        $this->applyResolvedVoucher($newVoucher, $basketViewModel, $frontendUser);
        return $this->redirect('show');
    }

    public function removeVoucherAction(string $voucherCode): ResponseInterface
    {
        $this->basketService->removeVoucherCode($this->request, $voucherCode);
        return $this->redirect('show');
    }

    private function applyResolvedVoucher(Voucher $newVoucher, BasketViewModel $basketViewModel, int $frontendUser): void
    {
        $existingCodes = $this->basketService->getAppliedVoucherCodes($this->request);
        $existingVouchers = $this->voucherService->buildDiscountSummary($existingCodes, $basketViewModel->getTotalGross(), $frontendUser)->getAppliedVouchers();
        if (!$this->voucherService->canCoexist($existingVouchers, $newVoucher)) {
            $this->basketService->clearVoucherCodes($this->request);
        }
        $this->basketService->addVoucherCode($this->request, $newVoucher->getCode());
    }

    /**
     * @param int[] $attributeValues Selected variant attribute-value uids (ignored if $article is set).
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
