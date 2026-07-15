<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Client;

use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Payment\Klarna\Configuration\KlarnaConfiguration;
use GoldeneZeiten\Products\Payment\Klarna\Domain\Dto\KlarnaHppSession;
use GoldeneZeiten\Products\Payment\Klarna\Domain\Dto\KlarnaHppStatus;
use GoldeneZeiten\Products\Payment\Klarna\Domain\Dto\KlarnaOrder;
use GoldeneZeiten\Products\Payment\Klarna\Exception\KlarnaApiException;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(KlarnaClient::class)]
final class HttpKlarnaClient implements KlarnaClient
{
    private const SESSIONS_PATH = '/payments/v1/sessions';

    private const HPP_SESSIONS_PATH = '/hpp/v1/sessions';

    private const PLACE_ORDER_PATH = '/payments/v1/authorizations/%s/order';

    public function __construct(
        private readonly ApiHttpClient $httpClient,
    ) {}

    public function createPaymentSession(array $sessionPayload, KlarnaConfiguration $configuration): string
    {
        $data = $this->decode($this->post($configuration->baseUrl() . self::SESSIONS_PATH, $sessionPayload, $configuration));
        $sessionId = (string)($data['session_id'] ?? '');
        if ($sessionId === '') {
            throw new KlarnaApiException('Klarna returned no payment session id.', 1752600600);
        }

        return $sessionId;
    }

    public function createHppSession(string $paymentSessionId, array $merchantUrls, KlarnaConfiguration $configuration): KlarnaHppSession
    {
        $payload = [
            'payment_session_url' => $configuration->baseUrl() . self::SESSIONS_PATH . '/' . $paymentSessionId,
            'merchant_urls' => $merchantUrls,
            'options' => [
                'place_order_mode' => 'NONE',
            ],
        ];
        $data = $this->decode($this->post($configuration->baseUrl() . self::HPP_SESSIONS_PATH, $payload, $configuration));

        return new KlarnaHppSession((string)($data['session_id'] ?? ''), (string)($data['redirect_url'] ?? ''));
    }

    public function readHppSession(string $hppSessionId, KlarnaConfiguration $configuration): KlarnaHppStatus
    {
        $url = $configuration->baseUrl() . self::HPP_SESSIONS_PATH . '/' . rawurlencode($hppSessionId);
        try {
            $response = $this->httpClient->get($url, $this->headers($configuration));
        } catch (ApiTransportException $exception) {
            throw new KlarnaApiException('Klarna session read failed at transport level.', 1752600601, $exception);
        }
        $data = $this->decode($response);

        return new KlarnaHppStatus((string)($data['status'] ?? ''), (string)($data['authorization_token'] ?? ''));
    }

    public function placeOrder(string $authorizationToken, array $orderPayload, KlarnaConfiguration $configuration): KlarnaOrder
    {
        $url = $configuration->baseUrl() . sprintf(self::PLACE_ORDER_PATH, rawurlencode($authorizationToken));
        $data = $this->decode($this->post($url, $orderPayload, $configuration));

        return new KlarnaOrder((string)($data['order_id'] ?? ''), (string)($data['fraud_status'] ?? ''));
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function post(string $url, array $payload, KlarnaConfiguration $configuration): ResponseInterface
    {
        try {
            return $this->httpClient->postJson($url, $payload, $this->headers($configuration));
        } catch (ApiTransportException $exception) {
            throw new KlarnaApiException('Klarna request failed at transport level.', 1752600602, $exception);
        }
    }

    /**
     * @return array<string, string>
     */
    private function headers(KlarnaConfiguration $configuration): array
    {
        return [
            'Authorization' => $configuration->authorizationHeader(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $status = $response->getStatusCode();
        $data = json_decode((string)$response->getBody(), true);
        if ($status !== 200 && $status !== 201) {
            throw new KlarnaApiException(sprintf('Klarna returned HTTP %d.', $status), 1752600603);
        }

        return is_array($data) ? $data : [];
    }
}
