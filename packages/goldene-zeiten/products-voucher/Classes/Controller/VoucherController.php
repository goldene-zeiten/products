<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Controller;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Voucher\Discount\VoucherCheckoutState;
use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use GoldeneZeiten\Products\Voucher\Service\Exception\VoucherExceptionInterface;
use GoldeneZeiten\Products\Voucher\Service\VoucherService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * The customer's voucher input, owned by the voucher feature rather than the basket. It keeps the codes
 * in the voucher's own slice of the checkout state and hands the customer back to the basket, so the
 * basket controller no longer has to know vouchers exist.
 */
final class VoucherController extends ActionController
{
    public function __construct(
        private readonly BasketService $basketService,
        private readonly VoucherService $voucherService,
        private readonly VoucherCheckoutState $voucherCheckoutState,
        private readonly FrontendUserResolver $frontendUserResolver
    ) {}

    public function applyAction(string $voucherCode): ResponseInterface
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
            return $this->redirectToBasket();
        }

        $this->applyResolvedVoucher($newVoucher, $basketViewModel->getTotalGross(), $frontendUser);
        return $this->redirectToBasket();
    }

    public function removeAction(string $voucherCode): ResponseInterface
    {
        $this->voucherCheckoutState->removeCode($this->request, $voucherCode);
        return $this->redirectToBasket();
    }

    private function applyResolvedVoucher(Voucher $newVoucher, Money $basketGoodsTotal, int $frontendUser): void
    {
        $existingCodes = $this->voucherCheckoutState->getCodes($this->request);
        $existingVouchers = $this->voucherService->buildDiscountSummary($existingCodes, $basketGoodsTotal, $frontendUser)->getAppliedVouchers();
        if (!$this->voucherService->canCoexist($existingVouchers, $newVoucher)) {
            $this->voucherCheckoutState->clearCodes($this->request);
        }
        $this->voucherCheckoutState->addCode($this->request, $newVoucher->getCode());
    }

    private function redirectToBasket(): ResponseInterface
    {
        return $this->redirect('show', 'Basket');
    }
}
