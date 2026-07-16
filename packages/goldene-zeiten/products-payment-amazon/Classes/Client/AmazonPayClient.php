<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Client;

use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfiguration;
use GoldeneZeiten\Products\Payment\Amazon\Domain\Dto\AmazonCheckoutSession;
use GoldeneZeiten\Products\Payment\Amazon\Exception\AmazonPayApiException;

/**
 * The Amazon Pay Checkout v2 calls the payment method needs across its two-hop redirect flow: create the
 * session, read it back after the buyer returns, update it with the final amount to obtain the second
 * redirect URL, and complete it to settle the charge. Split behind an interface so the payment method can
 * be tested against a fake without HTTP or signing.
 */
interface AmazonPayClient
{
    /**
     * @param array<string, mixed> $payload
     * @throws AmazonPayApiException
     */
    public function createCheckoutSession(array $payload, string $idempotencyKey, AmazonPayConfiguration $configuration): AmazonCheckoutSession;

    /**
     * @throws AmazonPayApiException
     */
    public function getCheckoutSession(string $checkoutSessionId, AmazonPayConfiguration $configuration): AmazonCheckoutSession;

    /**
     * @param array<string, mixed> $payload
     * @throws AmazonPayApiException
     */
    public function updateCheckoutSession(string $checkoutSessionId, array $payload, AmazonPayConfiguration $configuration): AmazonCheckoutSession;

    /**
     * @param array<string, mixed> $payload
     * @throws AmazonPayApiException
     */
    public function completeCheckoutSession(string $checkoutSessionId, array $payload, string $idempotencyKey, AmazonPayConfiguration $configuration): AmazonCheckoutSession;
}
