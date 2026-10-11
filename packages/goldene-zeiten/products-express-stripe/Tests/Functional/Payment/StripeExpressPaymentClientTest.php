<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\Stripe\Tests\Functional\Payment;

use GoldeneZeiten\Products\Core\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Express\Stripe\Tests\Functional\AbstractStripeExpressMockTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function aSucceededIntentReportsTheAmountStripeReceived(): void
    {
        $result = $this->client()->settle(10800, 'EUR', 'pm_card_visa', $this->configuration());
        $underpaid = $this->client()->settle(10800, 'EUR', 'pm_underpaid', $this->configuration());

        $this->assertSame(10800, $result->getSettledAmount()?->getCents());
        $this->assertSame(100, $underpaid->getSettledAmount()?->getCents());
    }

    #[Test]
    public function aSettledIntentCanBeRefundedInFull(): void
    {
        $this->client()->refund('pi_test_1', $this->configuration());

        $refunds = $this->loggedRequests('/express/stripe/v1/refunds');
        $this->assertCount(1, $refunds);
        parse_str((string)$refunds[0]['body'], $body);
        $this->assertSame('pi_test_1', $body['payment_intent']);
    }

    #[Test]
    #[DataProvider('minorUnitProvider')]
    public function theIntentIsCreatedInTheCurrencysMinorUnit(int $amountCents, string $currency, string $expectedAmount): void
    {
        $this->client()->settle($amountCents, $currency, 'pm_card_visa', $this->configuration());

        parse_str((string)$this->loggedRequests('/express/stripe/v1/payment_intents')[0]['body'], $body);
        $this->assertSame($expectedAmount, $body['amount']);
    }

    public static function minorUnitProvider(): \Generator
    {
        yield 'a two-decimal currency in cents' => ['amountCents' => 10500, 'currency' => 'EUR', 'expectedAmount' => '10500'];
        yield 'a zero-decimal currency in whole units' => ['amountCents' => 100000, 'currency' => 'JPY', 'expectedAmount' => '1000'];
        yield 'a three-decimal currency in thousandths' => ['amountCents' => 1234, 'currency' => 'BHD', 'expectedAmount' => '12340'];
    }

    #[Test]
    public function aDeclinedCardFailsWithoutAnOrder(): void
    {
        $result = $this->client()->settle(10500, 'EUR', 'pm_declined', $this->configuration());

        $this->assertSame(PaymentResultState::FAILED, $result->getState());
    }
}
