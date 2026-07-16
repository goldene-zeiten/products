<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Express\ExpressBasket;
use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContextItem;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\ExpressBasketTokenService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The express basket token is what lets the shipping-rate callback trust a basket it received outside the
 * session - so it must round-trip losslessly and reject anything edited after signing.
 */
final class ExpressBasketTokenServiceTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function aBasketRoundTripsThroughItsToken(): void
    {
        $service = $this->get(ExpressBasketTokenService::class);
        $basket = $this->basket();

        $resolved = $service->resolve($service->issue($basket));

        $this->assertInstanceOf(ExpressBasket::class, $resolved);
        $this->assertSame($basket->toArray(), $resolved->toArray());
    }

    #[Test]
    public function aTamperedTokenIsRejected(): void
    {
        $service = $this->get(ExpressBasketTokenService::class);
        $token = $service->issue($this->basket());

        // Change the first byte of the (signed) payload; the HMAC no longer matches.
        $tampered = ($token[0] === 'A' ? 'B' : 'A') . substr($token, 1);

        $this->assertNull($service->resolve($tampered));
    }

    #[Test]
    public function aMissingOrMalformedTokenIsRejected(): void
    {
        $service = $this->get(ExpressBasketTokenService::class);

        $this->assertNull($service->resolve(null));
        $this->assertNull($service->resolve('not-a-token'));
    }

    private function basket(): ExpressBasket
    {
        return new ExpressBasket(
            [new ShippingContextItem(2, 750, false, 'standard')],
            1500,
            Money::fromCents(10000),
            'EUR',
            0
        );
    }
}
