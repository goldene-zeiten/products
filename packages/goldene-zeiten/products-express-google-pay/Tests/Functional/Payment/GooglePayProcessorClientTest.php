<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Tests\Functional\Payment;

use GoldeneZeiten\Products\Express\GooglePay\Tests\Functional\AbstractGooglePayExpressMockTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The processor client's real HTTP path against the mock: authorize the Google Pay token, reporting
 * approval or decline as the processor answers.
 */
final class GooglePayProcessorClientTest extends AbstractGooglePayExpressMockTestCase
{
    #[Test]
    public function authorizesAnApprovedToken(): void
    {
        $authorization = $this->processorClient()->authorize('APPROVE-TOKEN', 10000, 'EUR', $this->configuration());

        $this->assertTrue($authorization->isApproved());
        $this->assertSame('GPAY-TXN-1', $authorization->getTransactionId());
    }

    #[Test]
    public function reportsADeclinedToken(): void
    {
        $authorization = $this->processorClient()->authorize('DECLINE-TOKEN', 10000, 'EUR', $this->configuration());

        $this->assertFalse($authorization->isApproved());
    }
}
