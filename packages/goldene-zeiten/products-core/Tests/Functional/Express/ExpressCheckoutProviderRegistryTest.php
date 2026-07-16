<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressCheckoutContext;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressCheckoutProviderNotFoundException;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The express seam discovers its providers by DI tag, exactly like the payment-method registry. The
 * fixture provider {@see \GoldeneZeiten\Products\PaymentFixture\FixtureExpressCheckoutProvider} is
 * available only for EUR, so both the discovery and the availability filter are exercised.
 */
final class ExpressCheckoutProviderRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    #[Test]
    public function anAvailableProviderIsDiscoveredForItsCurrency(): void
    {
        $providers = $this->get(ExpressCheckoutProviderRegistry::class)->getAvailable($this->context('EUR'));

        $identifiers = array_map(static fn($provider): string => $provider->getIdentifier(), $providers);
        $this->assertContains('fixture-express', $identifiers);
    }

    #[Test]
    public function aProviderIsFilteredOutWhenUnavailableForTheContext(): void
    {
        $providers = $this->get(ExpressCheckoutProviderRegistry::class)->getAvailable($this->context('USD'));

        $identifiers = array_map(static fn($provider): string => $provider->getIdentifier(), $providers);
        $this->assertNotContains('fixture-express', $identifiers);
    }

    #[Test]
    public function getReturnsTheProviderAndItsButtonConfiguration(): void
    {
        $provider = $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express');

        $this->assertSame('fixture-express', $provider->getIdentifier());
        $this->assertSame(
            ['provider' => 'fixture-express', 'amount' => 4990, 'currency' => 'EUR'],
            $provider->getButtonConfiguration($this->context('EUR'))
        );
    }

    #[Test]
    public function getThrowsForAnUnknownProvider(): void
    {
        $this->expectException(ExpressCheckoutProviderNotFoundException::class);
        $this->expectExceptionCode(1784220767);

        $this->get(ExpressCheckoutProviderRegistry::class)->get('does-not-exist');
    }

    private function context(string $currency): ExpressCheckoutContext
    {
        return new ExpressCheckoutContext(Money::fromCents(4990), $currency);
    }
}
