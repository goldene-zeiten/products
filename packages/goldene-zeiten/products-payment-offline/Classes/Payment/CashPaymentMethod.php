<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Offline\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\Offline\Settings\OfflinePaymentSettings;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Cash on pick-up: the customer pays in store when collecting the order, so it is only offered with the
 * shipping that means collecting. An optional discount rewards paying cash.
 */
final class CashPaymentMethod implements PaymentMethodInterface
{
    private const METHOD = 'cash';

    public function __construct(
        private readonly OfflinePaymentSettings $settings
    ) {}

    public function getIdentifier(): string
    {
        return self::METHOD;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_cash', 'ProductsPaymentOffline');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        $settings = $this->settings->forCurrentRequest();
        return $this->settings->isEnabled($settings, self::METHOD)
            && $context->isShippedWithAnyOf($this->settings->getShippingList($settings, self::METHOD, 'shipping', 'pickup'));
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function calculateFee(PaymentContext $context): int
    {
        return $this->settings->discount($this->settings->forCurrentRequest(), self::METHOD, $context);
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::PENDING, '');
    }
}
