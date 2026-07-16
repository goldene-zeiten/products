<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface;
use GoldeneZeiten\Products\Core\Middleware\ExpressShippingQuoteMiddleware;
use GoldeneZeiten\Products\Express\GooglePay\Configuration\GooglePayConfigurationFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Google Pay (the Google Pay API for Web) as a standalone express-checkout provider - no PSP wrapper. It
 * renders the Google Pay button on the cart page, quotes shipping live inside the sheet against the shop's
 * own carriers, and settles the token through the shop's own processor. Offered independently of any other
 * wallet.
 */
final class GooglePayExpressCheckoutProvider implements ExpressCheckoutProviderInterface
{
    public const IDENTIFIER = 'google-pay-express';

    /**
     * The currencies this provider is offered for. Anything else is hidden rather than failing once the
     * sheet opens.
     */
    private const SUPPORTED_CURRENCIES = [
        'AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK',
        'SGD', 'USD',
    ];

    public function __construct(
        private readonly GooglePayConfigurationFactory $configurationFactory
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('express_checkout_google_pay', 'ProductsExpressGooglePay');
    }

    public function isAvailable(ExpressCheckoutContext $context): bool
    {
        return $this->configurationFactory->forCurrentRequest()->isComplete()
            && in_array(strtoupper($context->getCurrency()), self::SUPPORTED_CURRENCIES, true);
    }

    public function getPriority(): int
    {
        return 20;
    }

    public function getButtonConfiguration(ExpressCheckoutContext $context): array
    {
        $configuration = $this->configurationFactory->forCurrentRequest();

        return [
            'provider' => self::IDENTIFIER,
            'environment' => $configuration->environment,
            'merchantId' => $configuration->merchantId,
            'merchantName' => $configuration->merchantName,
            'gateway' => $configuration->gateway,
            'gatewayMerchantId' => $configuration->gatewayMerchantId,
            'countryCode' => $configuration->countryCode,
            'amount' => $context->getAmount()->getCents(),
            'currency' => strtoupper($context->getCurrency()),
            'shippingQuoteUrl' => ExpressShippingQuoteMiddleware::PATH,
        ];
    }
}
