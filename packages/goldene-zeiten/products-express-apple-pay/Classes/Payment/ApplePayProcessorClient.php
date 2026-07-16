<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Express\ApplePay\Payment;

use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Express\ApplePay\Configuration\ApplePayConfiguration;
use GoldeneZeiten\Products\Express\ApplePay\Payment\Exception\ApplePayProcessorException;
use Psr\Http\Message\ResponseInterface;

/**
 * Talks to the merchant's own payment processor over the shared api-client HTTP client. Apple Pay needs two
 * server-to-server calls the browser cannot make itself: validating the merchant session (which requires
 * the Apple Pay merchant certificate the processor holds) and authorizing the encrypted payment token. Both
 * are a gateway-agnostic JSON contract so the shop points this at its own acquirer rather than a fixed PSP.
 */
final class ApplePayProcessorClient
{
    private const VALIDATE_PATH = '/applepay/merchant-validation';

    private const AUTHORIZE_PATH = '/applepay/authorize';

    public function __construct(
        private readonly ApiHttpClient $httpClient
    ) {}

    /**
     * @return array<string, mixed> the opaque Apple merchant session to hand back to the Apple Pay sheet
     */
    public function validateMerchant(string $validationUrl, string $domainName, ApplePayConfiguration $configuration): array
    {
        $payload = [
            'validationURL' => $validationUrl,
            'merchantIdentifier' => $configuration->merchantIdentifier,
            'displayName' => $configuration->displayName,
            'domainName' => $domainName,
        ];
        $response = $this->post($configuration->processorBaseUrl() . self::VALIDATE_PATH, $payload, $configuration);
        $data = json_decode((string)$response->getBody(), true);
        if ($response->getStatusCode() !== 200 || !is_array($data)) {
            throw new ApplePayProcessorException(sprintf('Apple Pay merchant validation returned HTTP %d.', $response->getStatusCode()), 1784220842);
        }

        return $data;
    }

    /**
     * @param array<string, mixed> $token the ApplePayPaymentToken (its paymentData is the encrypted blob)
     */
    public function authorize(array $token, int $amountCents, string $currency, ApplePayConfiguration $configuration): ApplePayAuthorization
    {
        $payload = [
            'token' => $token,
            'amount' => $amountCents,
            'currency' => $currency,
            'merchantIdentifier' => $configuration->merchantIdentifier,
        ];
        $response = $this->post($configuration->processorBaseUrl() . self::AUTHORIZE_PATH, $payload, $configuration);
        $data = json_decode((string)$response->getBody(), true);
        $data = is_array($data) ? $data : [];
        if ($response->getStatusCode() !== 200) {
            throw new ApplePayProcessorException(sprintf('Apple Pay authorization returned HTTP %d.', $response->getStatusCode()), 1784220844);
        }

        return new ApplePayAuthorization((string)($data['status'] ?? ''), (string)($data['transactionId'] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $url, array $payload, ApplePayConfiguration $configuration): ResponseInterface
    {
        try {
            return $this->httpClient->postJson($url, $payload, $this->headers($configuration));
        } catch (ApiTransportException $exception) {
            throw new ApplePayProcessorException('Apple Pay processor request failed at transport level.', 1784220840, $exception);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(ApplePayConfiguration $configuration): array
    {
        return $configuration->apiKey !== '' ? ['Authorization' => 'Bearer ' . $configuration->apiKey] : [];
    }
}
