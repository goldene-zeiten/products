<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Tests\Unit\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfigurationFactory;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayRegion;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class AmazonPayConfigurationFactoryTest extends UnitTestCase
{
    private const EXTENSION_DEFAULTS = [
        'region' => 'eu',
        'environment' => 'sandbox',
        'publicKeyId' => 'ext-public-key',
        'privateKey' => 'ext-private-key',
        'storeId' => 'ext-store-id',
        'merchantStoreName' => 'ext-store-name',
    ];

    #[Test]
    public function extensionConfigurationIsUsedWhenNoSiteOverrides(): void
    {
        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame(AmazonPayRegion::Eu, $configuration->region);
        $this->assertTrue($configuration->sandbox);
        $this->assertSame('ext-public-key', $configuration->publicKeyId);
        $this->assertSame('ext-private-key', $configuration->privateKey);
        $this->assertSame('ext-store-id', $configuration->storeId);
        $this->assertSame('ext-store-name', $configuration->merchantStoreName);
        $this->assertTrue($configuration->isComplete());
    }

    #[Test]
    public function siteSettingsOverrideExtensionConfigurationOnlyWhereSet(): void
    {
        $site = new Site('shop', 1, ['settings' => ['products' => ['payment' => ['amazon' => [
            'region' => 'na',
            'environment' => 'live',
            'publicKeyId' => 'site-public-key',
            // privateKey, storeId, merchantStoreName left empty -> inherited from extension configuration
        ]]]]]);

        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite($site);

        $this->assertSame(AmazonPayRegion::Na, $configuration->region);
        $this->assertFalse($configuration->sandbox);
        $this->assertSame('site-public-key', $configuration->publicKeyId);
        $this->assertSame('ext-private-key', $configuration->privateKey, 'Empty site value inherits the extension private key.');
        $this->assertSame('ext-store-id', $configuration->storeId, 'Empty site value inherits the extension store ID.');
        $this->assertSame('ext-store-name', $configuration->merchantStoreName, 'Empty site value inherits the extension store name.');
    }

    #[Test]
    public function anUnconfiguredExtensionYieldsAnIncompleteConfiguration(): void
    {
        $configuration = $this->factory([])->forSite(null);

        $this->assertSame('', $configuration->publicKeyId);
        $this->assertFalse($configuration->isComplete());
    }

    #[Test]
    public function apiBaseUrlOverridesTheRegionHostWhenSet(): void
    {
        $overridden = $this->factory(['apiBaseUrl' => 'http://localhost:8080/payment/amazon'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('http://localhost:8080/payment/amazon', $overridden->baseUrl());

        $default = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertStringContainsString('pay-api.amazon.eu', $default->baseUrl());
    }

    #[Test]
    public function sandboxSegmentIsIncludedInHostWhenSandboxIsTrue(): void
    {
        $sandboxConfig = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertTrue($sandboxConfig->sandbox);
        $this->assertStringContainsString('sandbox', $sandboxConfig->baseUrl());

        $liveConfig = $this->factory(['environment' => 'live'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertFalse($liveConfig->sandbox);
        $this->assertStringNotContainsString('sandbox', $liveConfig->baseUrl());
    }

    #[Test]
    public function regionDeterminesTheApiHost(): void
    {
        $euConfig = $this->factory(['region' => 'eu'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertStringContainsString('pay-api.amazon.eu', $euConfig->baseUrl());

        $naConfig = $this->factory(['region' => 'na'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertStringContainsString('pay-api.amazon.com', $naConfig->baseUrl());

        $jpConfig = $this->factory(['region' => 'jp'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertStringContainsString('pay-api.amazon.jp', $jpConfig->baseUrl());
    }

    /**
     * @param array<string, string|bool> $extensionConfiguration
     */
    private function factory(array $extensionConfiguration): AmazonPayConfigurationFactory
    {
        $extensionConfigurationService = $this->createMock(ExtensionConfiguration::class);
        if ($extensionConfiguration === []) {
            $extensionConfigurationService->method('get')
                ->willThrowException(new \RuntimeException('Extension not configured.', 1784198350));
        } else {
            $extensionConfigurationService->method('get')->willReturn($extensionConfiguration);
        }

        return new AmazonPayConfigurationFactory(
            new ApiSettingsResolver($extensionConfigurationService),
            new CurrentSiteResolver(),
        );
    }
}
