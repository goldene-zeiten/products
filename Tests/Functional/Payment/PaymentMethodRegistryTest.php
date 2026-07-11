<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Payment;

use GoldeneZeiten\Products\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class PaymentMethodRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function invoicePaymentMethodIsRegistered(): void
    {
        $this->assertSame('invoice', $this->get(PaymentMethodRegistry::class)->get('invoice')->getIdentifier());
    }

    #[Test]
    public function getThrowsExceptionForUnknownIdentifier(): void
    {
        $this->expectException(PaymentMethodNotFoundException::class);
        $this->expectExceptionCode(1751751010);

        $this->get(PaymentMethodRegistry::class)->get('unknown-payment-method');
    }
}
