<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Offline\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\Offline\Settings\OfflinePaymentSettings;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Cash on delivery: the carrier collects the amount, usually for a fee. Not offered for deliveries without
 * a carrier, such as pick-up in store.
 */
final class CashOnDeliveryPaymentMethod implements PaymentMethodInterface
{
    private const METHOD = 'cashOnDelivery';

    public function __construct(
        private readonly OfflinePaymentSettings $settings
    ) {}

    public function getIdentifier(): string
    {
        return 'cashondelivery';
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_cashondelivery', 'ProductsPaymentOffline');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        $settings = $this->settings->forCurrentRequest();
        return $this->settings->isEnabled($settings, self::METHOD)
            && !$context->isShippedWithAnyOf($this->settings->getShippingList($settings, self::METHOD, 'excludedShipping', 'pickup'));
    }

    public function getPriority(): int
    {
        return 0;
    }

    public function calculateFee(PaymentContext $context): int
    {
        $fee = $this->settings->getString($this->settings->forCurrentRequest(), self::METHOD, 'fee', '0.00');
        return Money::fromDecimalString(str_replace(',', '.', $fee !== '' ? $fee : '0.00'))->getCents();
    }

    public function initiate(Order $order, PaymentContext $context): PaymentResult
    {
        return PaymentResult::completed(PaymentStatus::PENDING, '');
    }
}
