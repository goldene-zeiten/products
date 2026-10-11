<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Configuration;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Service\PriceRoundingService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

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
    public function defaultCountryPricingModeAndCurrencyAreReadFromSiteSettings(): void
    {
        $site = new Site('products', 1, ['settings' => ['products' => [
            'tax' => ['defaultCountry' => 'AT'],
            'pricing' => ['mode' => 'net', 'currency' => 'CHF'],
        ]]]);
        $request = (new ServerRequest('http://localhost/'))->withAttribute('site', $site);

        $configuration = $this->subject()->create($request);

        $this->assertSame('AT', $configuration->getDefaultCountry());
        $this->assertSame('net', $configuration->getPricingMode());
        $this->assertSame('CHF', $configuration->getCurrency());
    }

    #[Test]
    public function defaultCountryPricingModeAndCurrencyFallBackToDefaultsWithoutASite(): void
    {
        $configuration = $this->subject()->create(new ServerRequest('http://localhost/'));

        $this->assertSame('DE', $configuration->getDefaultCountry());
        $this->assertSame('gross', $configuration->getPricingMode());
        $this->assertSame('EUR', $configuration->getCurrency());
    }

    #[Test]
    public function theFactoryNeverTouchesTheSharedConfigurationManager(): void
    {
        $constructor = (new \ReflectionClass(ProductsConfigurationFactory::class))->getConstructor();

        $this->assertNull($constructor);
    }

    private function subject(): ProductsConfigurationFactory
    {
        return new ProductsConfigurationFactory();
    }
}
