<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Tests\Unit\Configuration;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class ApiSettingsResolverTest extends UnitTestCase
{
    private const FIELDS = [
        'clientId',
        'clientSecret',
        'environment',
    ];

    private const EXTENSION_DEFAULTS = [
        'clientId' => 'ext-client',
        'clientSecret' => 'ext-secret',
        'environment' => 'sandbox',
    ];

    #[Test]
    public function extensionConfigurationIsUsedWhenNoSiteIsGiven(): void
    {
        $resolved = $this->resolver(self::EXTENSION_DEFAULTS)
            ->resolve('products_demo', 'products.demo.', self::FIELDS, null);

        $this->assertSame('ext-client', $resolved['clientId']);
        $this->assertSame('ext-secret', $resolved['clientSecret']);
        $this->assertSame('sandbox', $resolved['environment']);
    }

    #[Test]
    public function siteSettingsOverrideExtensionConfigurationOnlyWhereSet(): void
    {
        $site = new Site('shop', 1, ['settings' => ['products' => ['demo' => [
            'clientId' => 'site-client',
            'environment' => 'production',
            // clientSecret left empty -> inherited from the extension configuration
        ]]]]);

        $resolved = $this->resolver(self::EXTENSION_DEFAULTS)
            ->resolve('products_demo', 'products.demo.', self::FIELDS, $site);

        $this->assertSame('site-client', $resolved['clientId']);
        $this->assertSame('production', $resolved['environment']);
        $this->assertSame('ext-secret', $resolved['clientSecret'], 'Empty site value inherits the extension default.');
    }

    #[Test]
    public function anUnreadableExtensionConfigurationYieldsEmptyValues(): void
    {
        $resolved = $this->resolver(null)->resolve('products_demo', 'products.demo.', self::FIELDS, null);

        $this->assertSame('', $resolved['clientId']);
        $this->assertSame('', $resolved['clientSecret']);
        $this->assertSame('', $resolved['environment']);
    }

    #[Test]
    public function valuesAreTrimmed(): void
    {
        $resolved = $this->resolver(['clientId' => '  padded  '] + self::EXTENSION_DEFAULTS)
            ->resolve('products_demo', 'products.demo.', self::FIELDS, null);

        $this->assertSame('padded', $resolved['clientId']);
    }

    /**
     * @param array<string, string>|null $extensionConfiguration null makes the source throw, as an unconfigured extension would
     */
    private function resolver(?array $extensionConfiguration): ApiSettingsResolver
    {
        $extensionConfigurationService = $this->createMock(ExtensionConfiguration::class);
        if ($extensionConfiguration === null) {
            $extensionConfigurationService->method('get')
                ->willThrowException(new \RuntimeException('Extension not configured.', 1752600200));
        } else {
            $extensionConfigurationService->method('get')->willReturn($extensionConfiguration);
        }

        return new ApiSettingsResolver($extensionConfigurationService);
    }
}
