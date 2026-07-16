<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Tests\Functional\Express;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Middleware\ExpressShippingQuoteMiddleware;
use GoldeneZeiten\Products\Express\Stripe\Configuration\StripeExpressConfigurationFactory;
use GoldeneZeiten\Products\Express\Stripe\Express\StripeExpressCheckoutProvider;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class StripeExpressCheckoutProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-payment-stripe',
        'goldene-zeiten/products-express-stripe',
    ];

    #[Test]
    public function itIsAvailableWhenConfiguredForASupportedCurrency(): void
    {
        $this->assertTrue($this->subject(['publishableKey' => 'pk_test', 'secretKey' => 'sk_test'])->isAvailable($this->context('EUR')));
    }

    #[Test]
    public function itIsUnavailableForAnUnsupportedCurrency(): void
    {
        $this->assertFalse($this->subject(['publishableKey' => 'pk_test', 'secretKey' => 'sk_test'])->isAvailable($this->context('XPF')));
    }

    #[Test]
    public function itIsUnavailableWithoutBothKeys(): void
    {
        $this->assertFalse($this->subject(['publishableKey' => 'pk_test', 'secretKey' => ''])->isAvailable($this->context('EUR')));
    }

    #[Test]
    public function theButtonConfigurationCarriesThePublishableKeyAndCallbackUrl(): void
    {
        $config = $this->subject(['publishableKey' => 'pk_test_123', 'secretKey' => 'sk_test'])->getButtonConfiguration($this->context('EUR'));

        $this->assertSame('stripe-express', $config['provider']);
        $this->assertSame('pk_test_123', $config['publishableKey']);
        $this->assertSame('eur', $config['currency']);
        $this->assertSame(4990, $config['amount']);
        $this->assertSame(ExpressShippingQuoteMiddleware::PATH, $config['shippingQuoteUrl']);
    }

    /**
     * @param array<string, string> $extensionConfiguration
     */
    private function subject(array $extensionConfiguration): StripeExpressCheckoutProvider
    {
        $extensionConfigurationService = $this->createMock(ExtensionConfiguration::class);
        $extensionConfigurationService->method('get')->willReturn($extensionConfiguration);

        return new StripeExpressCheckoutProvider(
            new StripeExpressConfigurationFactory(new ApiSettingsResolver($extensionConfigurationService), new CurrentSiteResolver())
        );
    }

    private function context(string $currency): ExpressCheckoutContext
    {
        return new ExpressCheckoutContext(Money::fromCents(4990), $currency);
    }
}
