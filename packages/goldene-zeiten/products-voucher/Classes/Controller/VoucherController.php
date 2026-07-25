<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Controller;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Security\FrontendRequestTokenValidator;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Voucher\Discount\VoucherCheckoutState;
use GoldeneZeiten\Products\Voucher\Domain\Model\Voucher;
use GoldeneZeiten\Products\Voucher\Service\Exception\VoucherExceptionInterface;
use GoldeneZeiten\Products\Voucher\Service\VoucherRedemptionRateLimiter;
use GoldeneZeiten\Products\Voucher\Service\VoucherService;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * The customer's voucher input, owned by the voucher feature rather than the basket. It keeps the codes
 * in the voucher's own slice of the checkout state and hands the customer back to the basket, so the
 * basket controller no longer has to know vouchers exist.
 */
final class VoucherController extends ActionController
{
    private const REQUEST_TOKEN_SCOPE = 'products/voucher';

    public function __construct(
        private readonly BasketService $basketService,
        private readonly VoucherService $voucherService,
        private readonly VoucherCheckoutState $voucherCheckoutState,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly FrontendRequestTokenValidator $requestTokenValidator,
        private readonly VoucherRedemptionRateLimiter $rateLimiter
    ) {}

    public function applyAction(string $voucherCode): ResponseInterface
    {
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
        if (!$this->rateLimiter->isWithinLimit($this->request)) {
            $this->addFlashMessage(
                (string)LocalizationUtility::translate('voucher.rate_limited', 'ProductsVoucher'),
                '',
                ContextualFeedbackSeverity::ERROR
            );
            return $this->redirectToBasket();
        }
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
        if (($rejected = $this->rejectForgedRequest()) !== null) {
            return $rejected;
        }
        $this->voucherCheckoutState->removeCode($this->request, $voucherCode);
        return $this->redirectToBasket();
    }

    /**
     * A cross-site POST that cannot present the voucher forms' request token is bounced back to the basket
     * without touching the applied vouchers.
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
