<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Tests\Unit\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfigurationFactory;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressEnvironment;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class DhlExpressConfigurationFactoryTest extends UnitTestCase
{
    private const EXTENSION_DEFAULTS = [
        'environment' => 'sandbox',
        'accountNumber' => 'ACC123',
        'username' => 'ext-user',
        'password' => 'ext-pass',
        'originCountryCode' => 'DE',
        'originPostCode' => '53113',
        'originCityName' => 'Bonn',
        'weightUnit' => 'metric',
        'usedProducts' => '',
    ];

    #[Test]
    public function extensionConfigurationIsUsedWhenNoSiteOverrides(): void
    {
        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame(DhlExpressEnvironment::Sandbox, $configuration->environment);
        $this->assertSame('ext-user', $configuration->username);
        $this->assertSame('Bonn', $configuration->originCityName);
        $this->assertTrue($configuration->isComplete());
    }

    #[Test]
    public function siteSettingsOverrideExtensionConfigurationOnlyWhereSet(): void
    {
        $site = new Site('shop', 1, ['settings' => ['products' => ['shipping' => ['dhlexpress' => [
            'environment' => 'production',
            'username' => 'site-user',
            // password left empty -> inherited from the extension configuration
        ]]]]]);

        $configuration = $this->factory(self::EXTENSION_DEFAULTS)->forSite($site);

        $this->assertSame(DhlExpressEnvironment::Production, $configuration->environment);
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
    public function usedProductsAreParsedAndFilterProducts(): void
    {
        $configuration = $this->factory(['usedProducts' => 'P, U ,'] + self::EXTENSION_DEFAULTS)->forSite(null);

        $this->assertSame(['P', 'U'], $configuration->usedProducts);
        $this->assertTrue($configuration->offersProduct('P'));
        $this->assertFalse($configuration->offersProduct('K'));
    }

    #[Test]
    public function weightUnitFallsBackToMetricForAnythingButImperial(): void
    {
        $this->assertSame('imperial', $this->factory(['weightUnit' => 'imperial'] + self::EXTENSION_DEFAULTS)->forSite(null)->weightUnit);
        $this->assertSame('metric', $this->factory(['weightUnit' => 'nonsense'] + self::EXTENSION_DEFAULTS)->forSite(null)->weightUnit);
    }

    #[Test]
    public function apiBaseUrlOverridesTheEnvironmentHostWhenSet(): void
    {
        $overridden = $this->factory(['apiBaseUrl' => 'http://localhost:8080/shipping/dhl-express'] + self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('http://localhost:8080/shipping/dhl-express', $overridden->baseUrl());

        $default = $this->factory(self::EXTENSION_DEFAULTS)->forSite(null);
        $this->assertSame('https://express.api.dhl.com/mydhlapi/test', $default->baseUrl());
    }

    /**
     * @param array<string, string> $extensionConfiguration
     */
    private function factory(array $extensionConfiguration): DhlExpressConfigurationFactory
    {
        $extensionConfigurationService = $this->createMock(ExtensionConfiguration::class);
        if ($extensionConfiguration === []) {
            $extensionConfigurationService->method('get')
                ->willThrowException(new \RuntimeException('Extension not configured.', 1752600900));
        } else {
            $extensionConfigurationService->method('get')->willReturn($extensionConfiguration);
        }

        return new DhlExpressConfigurationFactory(
            new ApiSettingsResolver($extensionConfigurationService),
            new CurrentSiteResolver(),
        );
    }
}
