<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Payment;

use GoldeneZeiten\Products\Payment\Exception\PaymentMethodNotFoundException;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PaymentMethodRegistryTest extends FunctionalTestCase
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

    /**
     * @test
     */
    public function invoicePaymentMethodIsRegistered(): void
    {
        self::assertSame('invoice', $this->subject->get('invoice')->getIdentifier());
    }

    /**
     * @test
     */
    public function getThrowsExceptionForUnknownIdentifier(): void
    {
        $this->expectException(PaymentMethodNotFoundException::class);
        $this->expectExceptionCode(1751751010);

        $this->subject->get('unknown-payment-method');
    }
}
