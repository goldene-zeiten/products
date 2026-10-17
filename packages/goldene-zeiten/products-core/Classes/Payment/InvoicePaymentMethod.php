<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Site\Entity\SiteInterface;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Invoice has no gateway to call back; `cancel()`/`refund()` just acknowledge the money movement
 * happened outside the system.
 */
final class InvoicePaymentMethod implements PaymentMethodInterface, RefundablePaymentMethodInterface
{
    public function __construct(
        private readonly Context $context
    ) {}

    public function getIdentifier(): string
    {
        return 'invoice';
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_invoice', 'ProductsCore');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $site = $request instanceof ServerRequestInterface ? $request->getAttribute('site') : null;

        if (!$site instanceof SiteInterface) {
            return true;
        }
        $settings = $site->getSettings();
        $excludedShipping = array_filter(array_map(trim(...), explode(',', (string)$settings->get('products.payment.invoice.excludedShipping', ''))));
        return (bool)$settings->get('products.payment.invoice.enabled', true)
            && !$context->isShippedWithAnyOf($excludedShipping)
            && $this->isCustomerAllowed((string)$settings->get('products.payment.invoice.frontendUserGroups', ''));
    }

    /**
     * Invoice may be reserved for regular customers, i.e. members of chosen frontend user groups.
     */
    private function isCustomerAllowed(string $allowedGroups): bool
    {
        $allowed = array_map(intval(...), array_filter(array_map(trim(...), explode(',', $allowedGroups))));
        if ($allowed === []) {
            return true;
        }
        $customerGroups = $this->context->getPropertyFromAspect('frontend.user', 'groupIds', []);
        return array_intersect($allowed, is_array($customerGroups) ? array_map(intval(...), $customerGroups) : []) !== [];
    }

    /**
     * The default method every shop falls back to, so it sorts below anything an integrator registers.
     */
    public function getPriority(): int
    {
        return 0;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return 0;
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::PENDING, $order->getInvoiceNumber());
    }

    public function cancel(OrderData $order): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::FAILED, $order->invoiceNumber);
    }

    public function refund(OrderData $order, Money $amount): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::REFUNDED, $order->invoiceNumber);
    }
}
