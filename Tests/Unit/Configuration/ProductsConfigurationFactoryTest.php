<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Configuration;

use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Service\PriceRoundingService;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * `shipping`/`handling`/`pricing.roundingMode` are Site Settings
 * (Configuration/Sets/Products/settings.definitions.yaml) - ConfigurationManagerInterface never
 * bridges them (no `{$products:...}` reference exists in Configuration/TypoScript), so reading
 * them through it always silently returns the PHP-side fallback regardless of what a site editor
 * configures. `defaultCountry`/`pricingMode`/`currency` remain on ConfigurationManagerInterface
 * since legacy TypoScript genuinely bridges those into plugin.tx_products.settings.
 */
final class ProductsConfigurationFactoryTest extends UnitTestCase
{
    #[Test]
    public function shippingHandlingAndRoundingModeAreReadFromSiteSettings(): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => [
            'shipping' => ['enabled' => true, 'bulkySurcharge' => '2.50'],
            'handling' => ['enabled' => true],
            'pricing' => ['roundingMode' => 'nearestInteger'],
        ]]]);
        $request = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);

        $configuration = $this->subject()->create($request);

        $this->assertTrue($configuration->isShippingEnabled());
        $this->assertSame(250, $configuration->getBulkySurcharge()->getCents());
        $this->assertTrue($configuration->isHandlingEnabled());
        $this->assertSame('nearestInteger', $configuration->getRoundingMode());
    }

    #[Test]
    public function shippingHandlingAndRoundingModeDefaultToDisabledWithoutASite(): void
    {
        $configuration = $this->subject()->create(new ServerRequest('http://localhost/'));

        $this->assertFalse($configuration->isShippingEnabled());
        $this->assertSame(0, $configuration->getBulkySurcharge()->getCents());
        $this->assertFalse($configuration->isHandlingEnabled());
        $this->assertSame(PriceRoundingService::MODE_NONE, $configuration->getRoundingMode());
    }

    #[Test]
    public function defaultCountryPricingModeAndCurrencyStillComeFromExtbaseSettings(): void
    {
        $configuration = $this->subject([
            'tax' => ['defaultCountry' => 'AT'],
            'pricing' => ['mode' => 'net', 'currency' => 'CHF'],
        ])->create(new ServerRequest('http://localhost/'));

        $this->assertSame('AT', $configuration->getDefaultCountry());
        $this->assertSame('net', $configuration->getPricingMode());
        $this->assertSame('CHF', $configuration->getCurrency());
    }

    /**
     * @param array<string, mixed> $extbaseSettings
     */
    private function subject(array $extbaseSettings = []): ProductsConfigurationFactory
    {
        return new ProductsConfigurationFactory($this->fakeConfigurationManager($extbaseSettings));
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function fakeConfigurationManager(array $configuration): ConfigurationManagerInterface
    {
        return new class ($configuration) implements ConfigurationManagerInterface {
            /**
             * @param array<string, mixed> $configuration
             */
            public function __construct(private readonly array $configuration) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return $this->configuration;
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }
}
