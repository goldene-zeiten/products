<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Tests\Unit\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfigurationFactory;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalEnvironment;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class PaypalConfigurationFactoryTest extends UnitTestCase
{
    private const EXTENSION_DEFAULTS = [
        'environment' => 'sandbox',
        'clientId' => 'ext-client',
        'clientSecret' => 'ext-secret',
        'webhookId' => 'ext-webhook',
        'brandName' => 'Ext Shop',
    ];

    #[Test]
    public function extensionConfigurationIsUsedWhenNoSiteOverrides(): void
    {
        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame(PaypalEnvironment::Sandbox, $configuration->environment);
        $this->assertSame('ext-client', $configuration->clientId);
        $this->assertSame('ext-secret', $configuration->clientSecret);
        $this->assertSame('ext-webhook', $configuration->webhookId);
        $this->assertTrue($configuration->isComplete());
    }

    #[Test]
    public function siteSettingsOverrideExtensionConfigurationOnlyWhereSet(): void
    {
        $site = new Site('shop', 1, ['settings' => ['products' => ['payment' => ['paypal' => [
            'environment' => 'production',
            'clientId' => 'site-client',
            // clientSecret left empty -> inherited from the extension configuration
        ]]]]]);

        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite($site);

        $this->assertSame(PaypalEnvironment::Production, $configuration->environment);
        $this->assertSame('site-client', $configuration->clientId);
        $this->assertSame('ext-secret', $configuration->clientSecret, 'Empty site value inherits the extension secret.');
    }

    #[Test]
    public function anUnconfiguredExtensionYieldsAnIncompleteConfiguration(): void
    {
        $configuration = $this->factory([])->forSite(null);

        $this->assertSame('', $configuration->clientId);
        $this->assertFalse($configuration->isComplete());
    }

    #[Test]
    public function apiBaseUrlOverridesTheEnvironmentHostWhenSet(): void
    {
        $overridden = $this->factory(['apiBaseUrl' => 'http://localhost:8080/payment/paypal'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('http://localhost:8080/payment/paypal', $overridden->baseUrl());

        $default = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('https://api-m.sandbox.paypal.com', $default->baseUrl());
    }

    /**
     * @param array<string, string> $extensionConfiguration
     */
    private function factory(array $extensionConfiguration): PaypalConfigurationFactory
    {
        $extensionConfigurationService = $this->createMock(ExtensionConfiguration::class);
        if ($extensionConfiguration === []) {
            $extensionConfigurationService->method('get')
                ->willThrowException(new \RuntimeException('Extension not configured.', 1752600400));
        } else {
            $extensionConfigurationService->method('get')->willReturn($extensionConfiguration);
        }

        return new PaypalConfigurationFactory(
            new ApiSettingsResolver($extensionConfigurationService),
            new CurrentSiteResolver(),
        );
    }
}
