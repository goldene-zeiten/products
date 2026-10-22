<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Guide\Collector;

use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalSettingsFactory;
use GoldeneZeiten\Products\Core\Shipping\ShippingProviderRegistry;
use GoldeneZeiten\Products\Mcp\Guide\PageUrlResolver;
use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use TYPO3\CMS\Core\Country\CountryProvider;

/**
 * The variables of the checkout guide: the payment methods and shipping carriers the shop has, the countries
 * it delivers to and the legal pages the customer accepts or reads on the way.
 */
final readonly class CheckoutGuideCollector
{
    public function __construct(
        private PaymentMethodRegistry $paymentMethodRegistry,
        private ShippingProviderRegistry $shippingProviderRegistry,
        private CountryProvider $countryProvider,
        private PageUrlResolver $pageUrlResolver,
        private WithdrawalSettingsFactory $withdrawalSettingsFactory
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function collect(McpContext $context): array
    {
        $settings = $context->site->getSettings();

        return [
            'paymentMethods' => array_map(
                static fn(PaymentMethodInterface $method): string => $method->getLabel(),
                $this->paymentMethodRegistry->getAll()
            ),
            'shippingCarriers' => $this->shippingProviderRegistry->getIdentifiers(),
            'countries' => $this->countries((string)$settings->get('products.checkout.allowedCountries', '')),
            'withdrawalPeriodDays' => $this->withdrawalSettingsFactory->fromSettings($settings)->periodDays,
            'pages' => [
                'checkout' => $this->page($context, 'checkoutPage'),
                'basket' => $this->page($context, 'basketPage'),
                'terms' => $this->page($context, 'termsPage'),
                'privacy' => $this->page($context, 'privacyPage'),
                'withdrawal' => $this->page($context, 'withdrawalPage'),
                'withdrawalInstructions' => $this->page($context, 'withdrawalInstructionsPage'),
            ],
        ];
    }

    private function page(McpContext $context, string $setting): string
    {
        return $this->pageUrlResolver->resolve($context, (int)$context->site->getSettings()->get('products.pids.' . $setting, 0));
    }

    /**
     * @return array<int, array{code: string, name: string}> empty when the shop delivers everywhere
     */
    private function countries(string $allowed): array
    {
        $countries = [];
        foreach (array_filter(array_map(trim(...), explode(',', $allowed))) as $code) {
            $code = strtoupper($code);
            $countries[] = ['code' => $code, 'name' => $this->countryProvider->getByAlpha2IsoCode($code)?->getName() ?? $code];
        }

        return $countries;
    }
}
