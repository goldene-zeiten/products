<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Tests\Unit\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfigurationFactory;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaEnvironment;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class KlarnaConfigurationFactoryTest extends UnitTestCase
{
    private const EXTENSION_DEFAULTS = [
        'environment' => 'playground',
        'username' => 'ext-user',
        'password' => 'ext-pass',
    ];

    #[Test]
    public function extensionConfigurationIsUsedWhenNoSiteOverrides(): void
    {
        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame(KlarnaEnvironment::Playground, $configuration->environment);
        $this->assertSame('ext-user', $configuration->username);
        $this->assertSame('ext-pass', $configuration->password);
        $this->assertTrue($configuration->isComplete());
    }

    #[Test]
    public function siteSettingsOverrideExtensionConfigurationOnlyWhereSet(): void
    {
        $site = new Site('shop', 1, ['settings' => ['products' => ['payment' => ['klarna' => [
            'environment' => 'production',
            'username' => 'site-user',
            // password left empty -> inherited from the extension configuration
        ]]]]]);

        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite($site);

        $this->assertSame(KlarnaEnvironment::Production, $configuration->environment);
        $this->assertSame('site-user', $configuration->username);
        $this->assertSame('ext-pass', $configuration->password, 'Empty site value inherits the extension password.');
    }

    #[Test]
    public function anUnconfiguredExtensionYieldsAnIncompleteConfiguration(): void
    {
        $configuration = $this->factory([])->forSite(null);

        $this->assertSame('', $configuration->username);
        $this->assertFalse($configuration->isComplete());
    }

    #[Test]
    public function apiBaseUrlOverridesTheEnvironmentHostWhenSet(): void
    {
        $overridden = $this->factory(['apiBaseUrl' => 'http://localhost:8080/payment/klarna'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('http://localhost:8080/payment/klarna', $overridden->baseUrl());

        $default = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('https://api.playground.klarna.com', $default->baseUrl());
    }

    #[Test]
    public function theAuthorizationHeaderIsBasicOverTheCredentials(): void
    {
        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame('Basic ' . base64_encode('ext-user:ext-pass'), $configuration->authorizationHeader());
    }

    /**
     * @param array<string, string> $extensionConfiguration
     */
    private function factory(array $extensionConfiguration): KlarnaConfigurationFactory
    {
        $extensionConfigurationService = $this->createMock(ExtensionConfiguration::class);
        if ($extensionConfiguration === []) {
            $extensionConfigurationService->method('get')
                ->willThrowException(new \RuntimeException('Extension not configured.', 1752600700));
        } else {
            $extensionConfigurationService->method('get')->willReturn($extensionConfiguration);
        }

        return new KlarnaConfigurationFactory(
            new ApiSettingsResolver($extensionConfigurationService),
            new CurrentSiteResolver(),
        );
    }
}
