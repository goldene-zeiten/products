<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Tests\Functional\Shipping;

use GoldeneZeiten\Products\ApiClient\Configuration\ApiSettingsResolver;
use GoldeneZeiten\Products\ApiClient\Configuration\CurrentSiteResolver;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfigurationFactory;
use GoldeneZeiten\Products\Shipping\DhlExpress\Domain\Dto\DhlExpressRate;
use GoldeneZeiten\Products\Shipping\DhlExpress\Exception\DhlExpressRatingException;
use GoldeneZeiten\Products\Shipping\DhlExpress\Rating\DhlExpressRatingClient;
use GoldeneZeiten\Products\Shipping\DhlExpress\Shipping\DhlExpressShippingProvider;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class DhlExpressShippingProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
        'goldene-zeiten/products-shipping-dhl-express',
    ];

    #[Test]
    public function mapsRatesToPricedAndLabelledOptions(): void
    {
        $provider = $this->subject($this->ratingClient([
            new DhlExpressRate('P', 'EXPRESS WORLDWIDE', '42.50', 'EUR', '2026-07-22T12:00:00GMT+02:00'),
            new DhlExpressRate('U', 'ECONOMY SELECT', '24.90', 'EUR'),
        ]));

        $options = $provider->quote($this->context());

        $this->assertCount(2, $options);
        $this->assertSame('dhl:P', $options[0]->getKey());
        $this->assertSame('EXPRESS WORLDWIDE', $options[0]->getLabel());
        $this->assertSame(4250, $options[0]->getCost()->getCents());
        $this->assertStringContainsString('2026-07-22', $options[0]->getDeliveryEstimate());
    }

    #[Test]
    public function filtersProductsByTheAllowList(): void
    {
        $provider = $this->subject(
            $this->ratingClient([new DhlExpressRate('P', 'EXPRESS WORLDWIDE', '42.50', 'EUR'), new DhlExpressRate('U', 'ECONOMY SELECT', '24.90', 'EUR')]),
            usedProducts: 'P',
        );

        $options = $provider->quote($this->context());

        $this->assertCount(1, $options);
        $this->assertSame('dhl:P', $options[0]->getKey());
    }

    #[Test]
    public function skipsRatesQuotedInAnotherCurrency(): void
    {
        $provider = $this->subject($this->ratingClient([new DhlExpressRate('P', 'EXPRESS WORLDWIDE', '42.50', 'USD')]));

        $this->assertSame([], $provider->quote($this->context()));
    }

    #[Test]
    public function offersNothingWhenTheConfigurationIsIncomplete(): void
    {
        $provider = $this->subject($this->ratingClient([new DhlExpressRate('P', 'EXPRESS WORLDWIDE', '42.50', 'EUR')]), username: '');

        $this->assertSame([], $provider->quote($this->context()));
    }

    #[Test]
    public function offersNothingWhenRatingFails(): void
    {
        $provider = $this->subject($this->ratingClientThatThrows());

        $this->assertSame([], $provider->quote($this->context()));
    }

    #[Test]
    public function resolveReturnsTheChosenOptionOrNull(): void
    {
        $provider = $this->subject($this->ratingClient([
            new DhlExpressRate('P', 'EXPRESS WORLDWIDE', '42.50', 'EUR'),
            new DhlExpressRate('U', 'ECONOMY SELECT', '24.90', 'EUR'),
        ]));

        $this->assertSame('dhl:U', $provider->resolve('U', $this->context())?->getKey());
        $this->assertNull($provider->resolve('X', $this->context()));
    }

    private function subject(DhlExpressRatingClient $ratingClient, string $username = 'cid', string $usedProducts = ''): DhlExpressShippingProvider
    {
        $factory = new DhlExpressConfigurationFactory(
            new ApiSettingsResolver($this->extensionConfiguration([
                'environment' => 'sandbox',
                'accountNumber' => 'ACC',
                'username' => $username,
                'password' => 'secret',
                'originCountryCode' => 'DE',
                'originPostCode' => '53113',
                'originCityName' => 'Bonn',
                'weightUnit' => 'metric',
                'usedProducts' => $usedProducts,
            ])),
            new CurrentSiteResolver(),
        );

        return new DhlExpressShippingProvider($factory, $ratingClient, $this->get(EventDispatcherInterface::class), new NullLogger());
    }

    /**
     * @param DhlExpressRate[] $rates
     */
    private function ratingClient(array $rates): DhlExpressRatingClient
    {
        return new class ($rates) implements DhlExpressRatingClient {
            /**
             * @param DhlExpressRate[] $rates
             */
            public function __construct(private readonly array $rates) {}

            public function rate(ShippingContext $context, DhlExpressConfiguration $configuration): array
            {
                return $this->rates;
            }
        };
    }

    private function ratingClientThatThrows(): DhlExpressRatingClient
    {
        return new class () implements DhlExpressRatingClient {
            public function rate(ShippingContext $context, DhlExpressConfiguration $configuration): array
            {
                throw new DhlExpressRatingException('Simulated DHL Express rating failure for the fallback test.', 1752600802);
            }
        };
    }

    /**
     * @param array<string, string> $config
     */
    private function extensionConfiguration(array $config): ExtensionConfiguration
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration->method('get')->willReturn($config);

        return $extensionConfiguration;
    }

    private function context(): ShippingContext
    {
        return new ShippingContext([], 2500, Money::fromCents(5000), 'EUR', 'BE', '1000');
    }
}
