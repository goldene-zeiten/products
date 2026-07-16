<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Express\Stripe\Tests\Functional\AbstractStripeExpressMockTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Settling an express wallet payment goes over Stripe's real SDK and the PaymentIntent endpoint against the
 * shared WireMock mock: a succeeded intent means paid, a declined card means a failed result and no order.
 */
final class StripeExpressPaymentClientTest extends AbstractStripeExpressMockTestCase
{
    #[Test]
    public function aSucceededIntentSettlesToPaid(): void
    {
        $result = $this->client()->settle(10500, 'EUR', 'pm_card_visa', $this->configuration());

        $this->assertSame(PaymentStatus::PAID, $result->getPaymentStatus());
        $this->assertSame('pi_test_1', $result->getExternalId());
    }

    #[Test]
    public function aDeclinedCardFailsWithoutAnOrder(): void
    {
        $result = $this->client()->settle(10500, 'EUR', 'pm_declined', $this->configuration());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }
}
