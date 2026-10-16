<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Pickup\Tests\Functional\Shipping;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Shipping\Pickup\Shipping\PickupShippingProvider;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Every open branch is a shipping option of its own, free of charge, named with its address.
 */
final class PickupShippingProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-shipping-pickup',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/branches.csv');
    }

    #[Test]
    public function everyOpenBranchIsOfferedInItsBackendOrder(): void
    {
        $options = $this->get(PickupShippingProvider::class)->quote($this->context());

        $this->assertSame(['pickup:2', 'pickup:1'], array_map(static fn(ShippingOption $option): string => $option->getKey(), $options));
        $this->assertSame('Pick-up at Hamburg (Jungfernstieg 1, 20354 Hamburg)', $options[1]->getLabel());
        $this->assertSame(0, $options[1]->getCost()->getCents());
        $this->assertSame('Mon-Fri 10-18', $options[1]->getDeliveryEstimate());
    }

    #[Test]
    public function theChosenBranchIsResolvedAgain(): void
    {
        $option = $this->get(PickupShippingProvider::class)->resolve('2', $this->context());

        $this->assertSame('Pick-up at Munich (Marienplatz 8, 80331 München)', $option?->getLabel());
    }

    #[Test]
    public function aClosedOrUnknownBranchIsNotResolved(): void
    {
        $subject = $this->get(PickupShippingProvider::class);

        $this->assertNull($subject->resolve('3', $this->context()));
        $this->assertNull($subject->resolve('99', $this->context()));
        $this->assertNull($subject->resolve('1abc', $this->context()));
    }

    private function context(): ShippingContext
    {
        return new ShippingContext([], 0, Money::fromCents(10000), 'EUR', 'DE');
    }
}
