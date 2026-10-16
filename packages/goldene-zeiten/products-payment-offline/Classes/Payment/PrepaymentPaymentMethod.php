<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Offline\Payment;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Payment\PaymentInstructionsProviderInterface;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Service\OrderSettingsResolver;
use GoldeneZeiten\Products\Payment\Offline\Settings\OfflinePaymentSettings;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Prepayment by bank transfer: the order waits for the money; the confirmation tells the customer where to
 * transfer it. An optional discount rewards paying in advance.
 */
final class PrepaymentPaymentMethod implements PaymentMethodInterface, PaymentInstructionsProviderInterface
{
    private const METHOD = 'prepayment';

    public function __construct(
        private readonly OfflinePaymentSettings $settings,
        private readonly OrderSettingsResolver $orderSettingsResolver,
        private readonly LanguageServiceFactory $languageServiceFactory
    ) {}

    public function getIdentifier(): string
    {
        return self::METHOD;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('payment_method_prepayment', 'ProductsPaymentOffline');
    }

    public function isAvailable(PaymentContext $context): bool
    {
        $settings = $this->settings->forCurrentRequest();
        return $this->settings->isEnabled($settings, self::METHOD) && $this->settings->getString($settings, self::METHOD, 'iban') !== '';
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

    public function getPaymentInstructions(Order $order): string
    {
        $settings = $this->settings->forOrder($order);
        $locale = $this->orderSettingsResolver->getLocale($order);
        $amount = (new \NumberFormatter($locale !== '' ? $locale : 'en_US', \NumberFormatter::CURRENCY))
            ->formatCurrency($order->getTotalGross()->getCents() / 100, $order->getCurrency());
        $languageService = $this->languageServiceFactory->create($locale !== '' ? $locale : 'default');
        $bic = $this->settings->getString($settings, self::METHOD, 'bic');
        $bank = array_filter([
            $this->settings->getString($settings, self::METHOD, 'accountHolder'),
            'IBAN ' . $this->settings->getString($settings, self::METHOD, 'iban'),
            $bic !== '' ? 'BIC ' . $bic : '',
            $this->settings->getString($settings, self::METHOD, 'bankName'),
        ]);
        return sprintf(
            $languageService->sL('LLL:EXT:products_payment_offline/Resources/Private/Language/locallang.xlf:prepayment.instructions'),
            $amount,
            implode("\n", $bank),
            $order->getOrderNumber()
        );
    }
}
