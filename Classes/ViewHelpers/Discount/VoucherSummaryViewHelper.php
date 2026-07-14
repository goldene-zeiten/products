<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ViewHelpers\Discount;

use GoldeneZeiten\Products\Discount\Voucher\VoucherCheckoutState;
use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\ViewHelpers\Format\RenderingContextRequestResolverInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractViewHelper;

/**
 * The voucher discount the basket currently qualifies for, computed leniently: an applied code that has
 * since become invalid is left out of the summary rather than raising an error, because a basket page
 * only shows an estimate. Placement re-checks strictly when the order is booked.
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

    public function render(): BasketDiscountSummary
    {
        $request = $this->requestResolver->resolveRequest($this->renderingContext);
        if ($request === null) {
            return new BasketDiscountSummary([], Money::fromCents(0));
        }

        return $this->voucherService->buildDiscountSummary(
            $this->voucherCheckoutState->getCodes($request),
            $this->basketService->getBasketViewModel($request)->getTotalGross(),
            $this->frontendUserResolver->getUid($request)
        );
    }
}
