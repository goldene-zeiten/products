<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Tests\Functional\Payment;

use GoldeneZeiten\Products\Express\ApplePay\Tests\Functional\AbstractApplePayExpressMockTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The processor client's real HTTP path against the mock: validate the merchant session the sheet needs,
 * and authorize the token - reporting approval or decline as the processor answers.
 */
final class ApplePayProcessorClientTest extends AbstractApplePayExpressMockTestCase
{
    #[Test]
    public function validatesTheMerchantSession(): void
    {
        $session = $this->processorClient()->validateMerchant('https://apple-pay-gateway.apple.com/paymentservices/paymentSession', 'shop.example', $this->configuration());

        $this->assertSame('MOCK-MERCHANT-SESSION', $session['merchantSessionIdentifier']);
    }

    #[Test]
    public function authorizesAnApprovedToken(): void
    {
        $authorization = $this->processorClient()->authorize(['transactionIdentifier' => 'APPROVE'], 10000, 'EUR', $this->configuration());

        $this->assertTrue($authorization->isApproved());
        $this->assertSame('APPLE-TXN-1', $authorization->getTransactionId());
    }

    #[Test]
    public function reportsADeclinedToken(): void
    {
        $authorization = $this->processorClient()->authorize(['transactionIdentifier' => 'DECLINE'], 10000, 'EUR', $this->configuration());

        $this->assertFalse($authorization->isApproved());
    }
}
