<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Discount;

use GoldeneZeiten\Products\Discount\Voucher\VoucherCheckoutState;
use GoldeneZeiten\Products\Domain\Dto\Discount\VoucherBasketSummary;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\ViewHelpers\Format\RenderingContextRequestResolverInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * What the voucher part of the basket shows: the applied codes, what they take off, and what is left to
 * pay. Computed leniently - an applied code that has since become invalid is left out rather than raising
 * an error, because a basket page only shows an estimate. Placement re-checks strictly.
 *
 * The voucher basket partial owns this, so the basket controller no longer computes a discount summary
 * itself.
 */
final class VoucherSummaryViewHelper extends AbstractViewHelper
{
    public function __construct(
        private readonly BasketService $basketService,
        private readonly VoucherCheckoutState $voucherCheckoutState,
        private readonly VoucherService $voucherService,
        private readonly FrontendUserResolver $frontendUserResolver,
        private readonly RenderingContextRequestResolverInterface $requestResolver,
    ) {}

    public function render(): VoucherBasketSummary
    {
        $request = $this->requestResolver->resolveRequest($this->renderingContext);
        if ($request === null) {
            return new VoucherBasketSummary([], Money::fromCents(0), Money::fromCents(0));
        }

        $basketGoodsTotal = $this->basketService->getBasketViewModel($request)->getTotalGross();
        $summary = $this->voucherService->buildDiscountSummary(
            $this->voucherCheckoutState->getCodes($request),
            $basketGoodsTotal,
            $this->frontendUserResolver->getUid($request)
        );

        return new VoucherBasketSummary(
            $summary->getAppliedVouchers(),
            $summary->getDiscountTotal(),
            $basketGoodsTotal->subtract($summary->getDiscountTotal())
        );
    }
}
