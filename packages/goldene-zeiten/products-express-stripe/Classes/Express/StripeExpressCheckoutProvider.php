<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderInterface;
use GoldeneZeiten\Products\Core\Middleware\ExpressShippingQuoteMiddleware;
use GoldeneZeiten\Products\Express\Stripe\Configuration\StripeExpressConfigurationFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * Stripe's Express Checkout Element as an express-checkout provider. One element renders Apple Pay, Google
 * Pay, PayPal, Amazon Pay and Link, so a single provider covers every wallet - Stripe picks which button
 * to show per browser and device, and handles Apple Pay merchant validation and tokenisation.
 *
 * The provider hands the frontend the static configuration its button JS needs; the JS wires the element's
 * live shipping callback to the core {@see ExpressShippingQuoteMiddleware} and, on confirm, settles the
 * PaymentIntent and creates the order. The per-basket signed token is added by the plugin that renders the
 * button, since only it holds the live basket.
 */
final class StripeExpressCheckoutProvider implements ExpressCheckoutProviderInterface
{
    public const IDENTIFIER = 'stripe-express';

    /**
     * The currencies Stripe's Express Checkout Element is offered for. Offering it for anything else only
     * fails once the sheet opens.
     */
    private const SUPPORTED_CURRENCIES = [
        'EUR', 'USD', 'GBP', 'CHF', 'DKK', 'SEK', 'NOK', 'PLN', 'CZK', 'CAD', 'AUD', 'JPY', 'NZD', 'HKD', 'SGD',
    ];

    public function __construct(
        private readonly StripeExpressConfigurationFactory $configurationFactory
    ) {}

    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    public function getLabel(): string
    {
        return (string)LocalizationUtility::translate('express_checkout_stripe', 'ProductsExpressStripe');
    }

    public function isAvailable(ExpressCheckoutContext $context): bool
    {
        return $this->configurationFactory->forCurrentRequest()->isComplete()
            && in_array(strtoupper($context->getCurrency()), self::SUPPORTED_CURRENCIES, true);
    }

    public function getPriority(): int
    {
        return 50;
    }

    public function getButtonConfiguration(ExpressCheckoutContext $context): array
    {
        return [
            'provider' => self::IDENTIFIER,
            'publishableKey' => $this->configurationFactory->forCurrentRequest()->publishableKey,
            'amount' => $context->getAmount()->getCents(),
            'currency' => strtolower($context->getCurrency()),
            'shippingQuoteUrl' => ExpressShippingQuoteMiddleware::PATH,
        ];
    }
}
