<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Express;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressBasket;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\ExpressShippingQuoteService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The express quote reuses the real {@see ShippingQuoteService}, so the option and cost the wallet sheet
 * shows are the ones normal checkout would. The fixture carrier serves only "FX", which lets the served /
 * unserved / shipping-disabled cases be told apart.
 */
final class ExpressShippingQuoteServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    #[Test]
    public function aServedDestinationYieldsTheOptionAndItsOrderTotal(): void
    {
        $quote = $this->get(ExpressShippingQuoteService::class)->quote(
            $this->basket(),
            new Address(country: 'FX', zip: '12345'),
            $this->configuration(true)
        );

        $this->assertCount(1, $quote->getOptions());
        $option = $quote->getOptions()[0];
        $this->assertSame('fixture-shipping:standard', $option->getKey());
        $this->assertSame(500, $option->getShippingAmount()->getCents());
        // order total = goods (10000) + shipping (500)
        $this->assertSame(10500, $option->getOrderTotal()->getCents());
    }

    #[Test]
    public function anUnservedDestinationYieldsNoOptions(): void
    {
        $quote = $this->get(ExpressShippingQuoteService::class)->quote(
            $this->basket(),
            new Address(country: 'ZZ', zip: '00000'),
            $this->configuration(true)
        );

        $this->assertSame([], $quote->getOptions());
    }

    #[Test]
    public function disabledShippingYieldsNoOptions(): void
    {
        $quote = $this->get(ExpressShippingQuoteService::class)->quote(
            $this->basket(),
            new Address(country: 'FX', zip: '12345'),
            $this->configuration(false)
        );

        $this->assertSame([], $quote->getOptions());
    }

    private function basket(): ExpressBasket
    {
        return new ExpressBasket(
            [new ShippingContextItem(1, 1000, false, 'standard')],
            1000,
            Money::fromCents(10000),
            'EUR',
            0
        );
    }

    private function configuration(bool $shippingEnabled): ProductsConfiguration
    {
        return new ProductsConfiguration('FX', 'gross', 'EUR', $shippingEnabled, Money::fromCents(0), false, 'none', 900);
    }
}
