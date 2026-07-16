<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Amazon\Client;

use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Payment\Amazon\Configuration\AmazonPayConfiguration;
use GoldeneZeiten\Products\Payment\Amazon\Domain\Dto\AmazonCheckoutSession;
use GoldeneZeiten\Products\Payment\Amazon\Exception\AmazonPayApiException;
use GoldeneZeiten\Products\Payment\Amazon\Signing\AmazonPaySigner;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(AmazonPayClient::class)]
final class HttpAmazonPayClient implements AmazonPayClient
{
    private const CHECKOUT_SESSIONS = '/v2/checkoutSessions';

    public function __construct(
        private readonly ApiHttpClient $httpClient,
        private readonly AmazonPaySigner $signer,
    ) {}

    public function createCheckoutSession(array $payload, string $idempotencyKey, AmazonPayConfiguration $configuration): AmazonCheckoutSession
    {
        $url = $configuration->baseUrl() . self::CHECKOUT_SESSIONS;
        $headers = $this->signer->signedHeaders('POST', $url, $payload, $configuration, $idempotencyKey);

        return $this->interpret($this->postJson($url, $payload, $headers), [200, 201]);
    }

    public function getCheckoutSession(string $checkoutSessionId, AmazonPayConfiguration $configuration): AmazonCheckoutSession
    {
        $url = $this->sessionUrl($configuration, $checkoutSessionId);
        $headers = $this->signer->signedHeaders('GET', $url, [], $configuration);
        try {
            $response = $this->httpClient->get($url, $headers);
        } catch (ApiTransportException $exception) {
            throw new AmazonPayApiException('Amazon Pay session read failed at transport level.', 1784198347, $exception);
        }

        return $this->interpret($response, [200]);
    }

    public function updateCheckoutSession(string $checkoutSessionId, array $payload, AmazonPayConfiguration $configuration): AmazonCheckoutSession
    {
        $url = $this->sessionUrl($configuration, $checkoutSessionId);
        $headers = $this->signer->signedHeaders('PATCH', $url, $payload, $configuration);
        try {
            $response = $this->httpClient->patchJson($url, $payload, $headers);
        } catch (ApiTransportException $exception) {
            throw new AmazonPayApiException('Amazon Pay session update failed at transport level.', 1784198348, $exception);
        }

        return $this->interpret($response, [200]);
    }

    public function completeCheckoutSession(string $checkoutSessionId, array $payload, string $idempotencyKey, AmazonPayConfiguration $configuration): AmazonCheckoutSession
    {
        $url = $this->sessionUrl($configuration, $checkoutSessionId) . '/complete';
        $headers = $this->signer->signedHeaders('POST', $url, $payload, $configuration, $idempotencyKey);

        // 202 Accepted means the authorization is still pending at the network - a valid, non-error outcome.
        return $this->interpret($this->postJson($url, $payload, $headers), [200, 202]);
    }

    private function sessionUrl(AmazonPayConfiguration $configuration, string $checkoutSessionId): string
    {
        return $configuration->baseUrl() . self::CHECKOUT_SESSIONS . '/' . rawurlencode($checkoutSessionId);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, string> $headers
     */
    private function postJson(string $url, array $payload, array $headers): ResponseInterface
    {
        try {
            return $this->httpClient->postJson($url, $payload, $headers);
        } catch (ApiTransportException $exception) {
            throw new AmazonPayApiException('Amazon Pay request failed at transport level.', 1784198346, $exception);
        }
    }

    /**
     * @param int[] $acceptableStatuses
     */
    private function interpret(ResponseInterface $response, array $acceptableStatuses): AmazonCheckoutSession
    {
        $status = $response->getStatusCode();
        $data = json_decode((string)$response->getBody(), true);
        $data = is_array($data) ? $data : [];
        if (!in_array($status, $acceptableStatuses, true)) {
            $reasonCode = (string)($data['reasonCode'] ?? '');

            throw new AmazonPayApiException(
                sprintf('Amazon Pay returned HTTP %d (%s).', $status, $reasonCode !== '' ? $reasonCode : 'unknown'),
                1784198349
            );
        }

        return AmazonCheckoutSession::fromArray($data);
    }
}
