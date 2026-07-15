<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Stripe\Tests\Unit\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Payment\Stripe\Configuration\StripeConfigurationFactory;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class StripeConfigurationFactoryTest extends UnitTestCase
{
    private const EXTENSION_DEFAULTS = [
        'secretKey' => 'sk_test_ext',
        'webhookSecret' => 'whsec_ext',
    ];

    #[Test]
    public function extensionConfigurationIsUsedWhenNoSiteOverrides(): void
    {
        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame('sk_test_ext', $configuration->secretKey);
        $this->assertSame('whsec_ext', $configuration->webhookSecret);
        $this->assertTrue($configuration->isComplete());
    }

    #[Test]
    public function siteSettingsOverrideExtensionConfigurationOnlyWhereSet(): void
    {
        $site = new Site('shop', 1, ['settings' => ['products' => ['payment' => ['stripe' => [
            'secretKey' => 'sk_test_site',
            // webhookSecret left empty -> inherited from the extension configuration
        ]]]]]);

        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite($site);

        $this->assertSame('sk_test_site', $configuration->secretKey);
        $this->assertSame('whsec_ext', $configuration->webhookSecret, 'Empty site value inherits the extension secret.');
    }

    #[Test]
    public function anUnconfiguredExtensionYieldsAnIncompleteConfiguration(): void
    {
        $configuration = $this->factory([])->forSite(null);

        $this->assertSame('', $configuration->secretKey);
        $this->assertFalse($configuration->isComplete());
    }

    #[Test]
    public function apiBaseUrlOverridesTheStripeHostWhenSet(): void
    {
        $overridden = $this->factory(['apiBaseUrl' => 'http://localhost:8080/payment/stripe'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('http://localhost:8080/payment/stripe', $overridden->baseUrl());

        $default = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('https://api.stripe.com', $default->baseUrl());
    }

    /**
     * @param array<string, string> $extensionConfiguration
     */
    private function factory(array $extensionConfiguration): StripeConfigurationFactory
    {
        $extensionConfigurationService = $this->createMock(ExtensionConfiguration::class);
        if ($extensionConfiguration === []) {
            $extensionConfigurationService->method('get')
                ->willThrowException(new \RuntimeException('Extension not configured.', 1752600500));
        } else {
            $extensionConfigurationService->method('get')->willReturn($extensionConfiguration);
        }

        return new StripeConfigurationFactory(
            new ApiSettingsResolver($extensionConfigurationService),
            new CurrentSiteResolver(),
        );
    }
}
