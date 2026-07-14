<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class PaymentMethodRegistryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    #[Test]
    public function invoicePaymentMethodIsRegistered(): void
    {
        $subject = $this->get(PaymentMethodRegistry::class);

        $this->assertSame('invoice', $subject->get('invoice')->getIdentifier());
    }

    #[Test]
    public function getThrowsExceptionForUnknownIdentifier(): void
    {
        $subject = $this->get(PaymentMethodRegistry::class);
        $this->expectException(PaymentMethodNotFoundException::class);
        $this->expectExceptionCode(1751751010);

        $subject->get('unknown-payment-method');
    }
}
