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

    private PaymentMethodRegistry $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(PaymentMethodRegistry::class);
    }

    #[Test]
    public function invoicePaymentMethodIsRegistered(): void
    {
        self::assertSame('invoice', $this->subject->get('invoice')->getIdentifier());
    }

    #[Test]
    public function getThrowsExceptionForUnknownIdentifier(): void
    {
        $this->expectException(PaymentMethodNotFoundException::class);
        $this->expectExceptionCode(1751751010);

        $this->subject->get('unknown-payment-method');
    }
}
