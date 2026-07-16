<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\GooglePay\Payment;

use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Express\GooglePay\Configuration\GooglePayConfiguration;
use GoldeneZeiten\Products\Express\GooglePay\Payment\Exception\GooglePayProcessorException;

/**
 * Authorizes a Google Pay token through the merchant's own payment processor over the shared api-client
 * HTTP client. Google Pay hands the browser a token already encrypted for the tokenization gateway; this
 * forwards it to the processor to charge, over a gateway-agnostic JSON contract so the shop settles through
 * its own acquirer rather than a fixed PSP.
 */
final class GooglePayProcessorClient
{
    private const AUTHORIZE_PATH = '/googlepay/authorize';

    public function __construct(
        private readonly ApiHttpClient $httpClient
    ) {}

    public function authorize(string $token, int $amountCents, string $currency, GooglePayConfiguration $configuration): GooglePayAuthorization
    {
        $payload = [
            'token' => $token,
            'amount' => $amountCents,
            'currency' => $currency,
            'gatewayMerchantId' => $configuration->gatewayMerchantId,
        ];
        try {
            $response = $this->httpClient->postJson($configuration->processorBaseUrl() . self::AUTHORIZE_PATH, $payload, $this->headers($configuration));
        } catch (ApiTransportException $exception) {
            throw new GooglePayProcessorException('Google Pay authorization failed at transport level.', 1784220860, $exception);
        }
        $data = json_decode((string)$response->getBody(), true);
        $data = is_array($data) ? $data : [];
        if ($response->getStatusCode() !== 200) {
            throw new GooglePayProcessorException(sprintf('Google Pay authorization returned HTTP %d.', $response->getStatusCode()), 1784220861);
        }

        return new GooglePayAuthorization((string)($data['status'] ?? ''), (string)($data['transactionId'] ?? ''));
    }

    /**
     * @return array<string, string>
     */
    private function headers(GooglePayConfiguration $configuration): array
    {
        return $configuration->apiKey !== '' ? ['Authorization' => 'Bearer ' . $configuration->apiKey] : [];
    }
}
