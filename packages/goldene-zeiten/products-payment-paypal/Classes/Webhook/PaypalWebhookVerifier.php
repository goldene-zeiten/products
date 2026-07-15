<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Webhook;

use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider;
use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Payment\Paypal\Authentication\PaypalCredentialsFactory;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfiguration;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Verifies an incoming PayPal webhook against PayPal itself, so a forged or replayed notification cannot
 * mark an order paid. A webhook is trusted only when PayPal confirms the transmission signature for the
 * configured webhook id; without a configured webhook id nothing can be verified, so nothing is trusted.
 */
final class PaypalWebhookVerifier
{
    private const VERIFY_PATH = '/v1/notifications/verify-webhook-signature';

    /**
     * The transmission headers PayPal signs the webhook with, mapped to the verify-request field names.
     */
    private const SIGNATURE_HEADERS = [
        'auth_algo' => 'PayPal-Auth-Algo',
        'cert_url' => 'PayPal-Cert-Url',
        'transmission_id' => 'PayPal-Transmission-Id',
        'transmission_sig' => 'PayPal-Transmission-Sig',
        'transmission_time' => 'PayPal-Transmission-Time',
    ];

    public function __construct(
        private readonly ApiHttpClient $httpClient,
        private readonly OAuth2ClientCredentialsProvider $tokenProvider,
        private readonly PaypalCredentialsFactory $credentialsFactory,
    ) {}

    public function isSignatureValid(ServerRequestInterface $request, string $body, PaypalConfiguration $configuration): bool
    {
        if ($configuration->webhookId === '') {
            return false;
        }

        try {
            $response = $this->verify($this->buildPayload($request, $body, $configuration), $configuration);
        } catch (ApiTransportException) {
            return false;
        }
        if ($response->getStatusCode() !== 200) {
            return false;
        }

        $data = json_decode((string)$response->getBody(), true);

        return is_array($data) && ($data['verification_status'] ?? '') === 'SUCCESS';
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(ServerRequestInterface $request, string $body, PaypalConfiguration $configuration): array
    {
        $payload = [
            'webhook_id' => $configuration->webhookId,
            'webhook_event' => json_decode($body, true),
        ];
        foreach (self::SIGNATURE_HEADERS as $field => $header) {
            $payload[$field] = $request->getHeaderLine($header);
        }

        return $payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function verify(array $payload, PaypalConfiguration $configuration): ResponseInterface
    {
        $token = $this->tokenProvider->getToken($this->credentialsFactory->forConfiguration($configuration));

        return $this->httpClient->postJson(
            $configuration->baseUrl() . self::VERIFY_PATH,
            $payload,
            ['Authorization' => 'Bearer ' . $token],
        );
    }
}
