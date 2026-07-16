<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface;
use GoldeneZeiten\Products\Core\Middleware\ExpressShippingQuoteMiddleware;
use GoldeneZeiten\Products\Express\ApplePay\Configuration\ApplePayConfigurationFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Apple Pay (the raw Apple Pay JS `ApplePaySession`) as a standalone express-checkout provider - no PSP
 * wrapper. It renders the Apple Pay button on the cart page, quotes shipping live inside the sheet against
 * the shop's own carriers, and settles the encrypted token through the shop's own processor. Offered
 * independently of any other wallet, for shops that want Apple Pay on its own merchant account.
 */
final class ApplePayExpressCheckoutProvider implements ExpressCheckoutProviderInterface
{
    public const IDENTIFIER = 'apple-pay-express';

    /**
     * The currencies this provider is offered for. Anything else is hidden rather than failing once the
     * sheet opens.
     */
    private const SUPPORTED_CURRENCIES = [
        'AUD', 'CAD', 'CHF', 'CZK', 'DKK', 'EUR', 'GBP', 'HKD', 'HUF', 'JPY', 'NOK', 'NZD', 'PLN', 'SEK',
        'SGD', 'USD',
    ];

    public function __construct(
        private readonly ApplePayConfigurationFactory $configurationFactory
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('express_checkout_apple_pay', 'ProductsExpressApplePay');
    }

    public function isAvailable(ExpressCheckoutContext $context): bool
    {
        return $this->configurationFactory->forCurrentRequest()->isComplete()
            && in_array(strtoupper($context->getCurrency()), self::SUPPORTED_CURRENCIES, true);
    }

    public function getPriority(): int
    {
        return 30;
    }

    public function getButtonConfiguration(ExpressCheckoutContext $context): array
    {
        $configuration = $this->configurationFactory->forCurrentRequest();

        return [
            'provider' => self::IDENTIFIER,
            'merchantIdentifier' => $configuration->merchantIdentifier,
            'displayName' => $configuration->displayName,
            'countryCode' => $configuration->countryCode,
            'amount' => $context->getAmount()->getCents(),
            'currency' => strtoupper($context->getCurrency()),
            'shippingQuoteUrl' => ExpressShippingQuoteMiddleware::PATH,
        ];
    }
}
