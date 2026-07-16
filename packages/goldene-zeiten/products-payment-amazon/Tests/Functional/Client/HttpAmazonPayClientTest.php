<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Tests\Functional\Client;

use GoldeneZeiten\Products\Payment\Amazon\Tests\Functional\AbstractAmazonPayMockTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Exercises every Amazon Pay call the payment method makes over the real HTTP + SDK-signing path against
 * the shared WireMock mock: create, read, update (a genuine PATCH), and complete.
 */
final class HttpAmazonPayClientTest extends AbstractAmazonPayMockTestCase
{
    #[Test]
    public function createsACheckoutSessionAndReturnsItOpen(): void
    {
        $session = $this->client()->createCheckoutSession(
            ['storeId' => 'amzn1.application-oa2-client.test'],
            'idem-create-1',
            $this->configuration()
        );

        $this->assertSame('amzn_session_1', $session->checkoutSessionId);
        $this->assertTrue($session->isOpen());
    }

    #[Test]
    public function readsBackACheckoutSession(): void
    {
        $session = $this->client()->getCheckoutSession('amzn_session_1', $this->configuration());

        $this->assertSame('amzn_session_1', $session->checkoutSessionId);
        $this->assertTrue($session->isOpen());
    }

    #[Test]
    public function updatingWithThePatchYieldsTheSecondRedirectUrl(): void
    {
        $session = $this->client()->updateCheckoutSession(
            'amzn_session_1',
            ['paymentDetails' => ['chargeAmount' => ['amount' => '54.90', 'currencyCode' => 'EUR']]],
            $this->configuration()
        );

        $this->assertSame('https://pay.amazon.eu/checkout/amzn_session_1', $session->amazonPayRedirectUrl);
    }

    #[Test]
    public function completingSettlesTheChargeAndReturnsTheChargeId(): void
    {
        $session = $this->client()->completeCheckoutSession(
            'amzn_session_1',
            ['chargeAmount' => ['amount' => '54.90', 'currencyCode' => 'EUR']],
            'idem-complete-1',
            $this->configuration()
        );

        $this->assertTrue($session->isCompleted());
        $this->assertSame('S01-0000000-0000000-C000000', $session->chargeId);
        $this->assertSame('S01-0000000-0000000', $session->chargePermissionId);
    }

    #[Test]
    public function everyRequestCarriesAnRsaSignedAuthorizationHeader(): void
    {
        $this->client()->getCheckoutSession('amzn_session_1', $this->configuration());

        $logged = $this->loggedRequests('/payment/amazon/v2/checkoutSessions/amzn_session_1', 'GET');
        $this->assertNotEmpty($logged);
        $headers = $logged[array_key_last($logged)]['headers'] ?? [];
        $authorization = $this->headerValue($headers, 'Authorization');
        $this->assertStringContainsString('AMZN-PAY-RSASSA-PSS', $authorization);
        $this->assertStringContainsString('PublicKeyId=SANDBOX-AMZN-TEST-KEY', $authorization);
    }

    /**
     * WireMock records header names as sent (lowercase here); read case-insensitively.
     *
     * @param array<string, mixed> $headers
     */
    private function headerValue(array $headers, string $name): string
    {
        foreach ($headers as $key => $value) {
            if (strtolower((string)$key) === strtolower($name)) {
                return is_array($value) ? implode(',', $value) : (string)$value;
            }
        }

        return '';
    }
}
