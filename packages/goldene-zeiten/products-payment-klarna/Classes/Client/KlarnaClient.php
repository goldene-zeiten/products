<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Client;

use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfiguration;
use GoldeneZeiten\Products\Payment\Klarna\Domain\Dto\KlarnaHppSession;
use GoldeneZeiten\Products\Payment\Klarna\Domain\Dto\KlarnaHppStatus;
use GoldeneZeiten\Products\Payment\Klarna\Domain\Dto\KlarnaOrder;
use GoldeneZeiten\Products\Payment\Klarna\Exception\KlarnaApiException;

/**
 * The Klarna Payments + Hosted Payment Page calls the payment method needs: open a payment session, wrap
 * it in a hosted-page session the customer is redirected to, read that session back to learn the outcome,
 * and place the order once the customer has authorized it. Split behind an interface so the payment method
 * can be tested against a fake without HTTP.
 */
interface KlarnaClient
{
    /**
     * @param array<string, mixed> $sessionPayload
     * @throws KlarnaApiException
     */
    public function createPaymentSession(array $sessionPayload, KlarnaConfiguration $configuration): string;

    /**
     * @param array<string, string> $merchantUrls
     * @throws KlarnaApiException
     */
    public function createHppSession(string $paymentSessionId, array $merchantUrls, KlarnaConfiguration $configuration): KlarnaHppSession;

    /**
     * @throws KlarnaApiException
     */
    public function readHppSession(string $hppSessionId, KlarnaConfiguration $configuration): KlarnaHppStatus;

    /**
     * @param array<string, mixed> $orderPayload
     * @throws KlarnaApiException
     */
    public function placeOrder(string $authorizationToken, array $orderPayload, KlarnaConfiguration $configuration): KlarnaOrder;
}
