<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Paypal\Order;

use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider;
use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentContext;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\Paypal\Authentication\PaypalCredentialsFactory;
use GoldeneZeiten\Products\Payment\Paypal\Configuration\PaypalConfiguration;
use GoldeneZeiten\Products\Payment\Paypal\Domain\Dto\PaypalCapture;
use GoldeneZeiten\Products\Payment\Paypal\Domain\Dto\PaypalOrder;
use GoldeneZeiten\Products\Payment\Paypal\Event\ModifyPaypalOrderRequestEvent;
use GoldeneZeiten\Products\Payment\Paypal\Exception\PaypalApiException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(PaypalOrderClient::class)]
final class HttpPaypalOrderClient implements PaypalOrderClient
{
    private const ORDERS_PATH = '/v2/checkout/orders';

    /**
     * The link relation the customer follows to approve the payment. PayPal returns `payer-action` for the
     * `payment_source.paypal` flow and `approve` for the classic one; accept either.
     */
    private const APPROVE_RELATIONS = [
        'payer-action',
        'approve',
    ];

    public function __construct(
        private readonly ApiHttpClient $httpClient,
        private readonly OAuth2ClientCredentialsProvider $tokenProvider,
        private readonly PaypalCredentialsFactory $credentialsFactory,
        private readonly PaypalOrderRequestBuilder $requestBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function createOrder(Order $order, PaymentContext $context, PaypalConfiguration $configuration): PaypalOrder
    {
        $payload = $this->buildPayload($order, $context, $configuration);
        $response = $this->authorizedPost($configuration->baseUrl() . self::ORDERS_PATH, $payload, $configuration);

        return $this->toOrder($response);
    }

    public function capture(string $paypalOrderId, PaypalConfiguration $configuration): PaypalCapture
    {
        $url = $configuration->baseUrl() . self::ORDERS_PATH . '/' . rawurlencode($paypalOrderId) . '/capture';
        $response = $this->authorizedPost($url, null, $configuration);
        $data = $this->decode($response);
        if ($response->getStatusCode() === 422 && $this->hasIssue($data, 'ORDER_ALREADY_CAPTURED')) {
            // A replayed return: the order was captured on the first pass. The 422 body carries no capture
            // details, so re-read the order and let the caller verify it still belongs to this order.
            return $this->show($paypalOrderId, $configuration);
        }
        if ($response->getStatusCode() !== 200 && $response->getStatusCode() !== 201) {
            throw new PaypalApiException(sprintf('PayPal capture returned HTTP %d.', $response->getStatusCode()), 1752600302);
        }

        return $this->toCapture($data);
    }

    public function show(string $paypalOrderId, PaypalConfiguration $configuration): PaypalCapture
    {
        $url = $configuration->baseUrl() . self::ORDERS_PATH . '/' . rawurlencode($paypalOrderId);
        $response = $this->authorizedGet($url, $configuration);
        if ($response->getStatusCode() !== 200) {
            throw new PaypalApiException(sprintf('PayPal order lookup returned HTTP %d.', $response->getStatusCode()), 1752600303);
        }

        return $this->toCapture($this->decode($response));
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(Order $order, PaymentContext $context, PaypalConfiguration $configuration): array
    {
        $event = new ModifyPaypalOrderRequestEvent(
            $this->requestBuilder->build($order, $context, $configuration),
            $order,
            $context,
            $configuration,
        );
        $this->eventDispatcher->dispatch($event);

        return $event->getPayload();
    }

    /**
     * @param array<string, mixed>|null $payload null sends an empty body (capture takes its input from the URL)
     */
    private function authorizedPost(string $url, ?array $payload, PaypalConfiguration $configuration): ResponseInterface
    {
        $credentials = $this->credentialsFactory->forConfiguration($configuration);
        $response = $this->dispatch($url, $payload, $this->tokenProvider->getToken($credentials));
        if ($response->getStatusCode() === 401) {
            // The cached token was rejected (PayPal can revoke early); retry once with a fresh one.
            $response = $this->dispatch($url, $payload, $this->tokenProvider->getToken($credentials, true));
        }

        return $response;
    }

    private function authorizedGet(string $url, PaypalConfiguration $configuration): ResponseInterface
    {
        $credentials = $this->credentialsFactory->forConfiguration($configuration);
        $response = $this->dispatchGet($url, $this->tokenProvider->getToken($credentials));
        if ($response->getStatusCode() === 401) {
            $response = $this->dispatchGet($url, $this->tokenProvider->getToken($credentials, true));
        }

        return $response;
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function dispatch(string $url, ?array $payload, string $token): ResponseInterface
    {
        $headers = [
            'Authorization' => 'Bearer ' . $token,
        ];

        try {
            return $payload === null
                ? $this->httpClient->post($url, $headers)
                : $this->httpClient->postJson($url, $payload, $headers);
        } catch (ApiTransportException $exception) {
            throw new PaypalApiException('PayPal request failed at transport level.', 1752600300, $exception);
        }
    }

    private function dispatchGet(string $url, string $token): ResponseInterface
    {
        try {
            return $this->httpClient->get($url, ['Authorization' => 'Bearer ' . $token]);
        } catch (ApiTransportException $exception) {
            throw new PaypalApiException('PayPal request failed at transport level.', 1752600304, $exception);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $data = json_decode((string)$response->getBody(), true);

        return is_array($data) ? $data : [];
    }

    private function toOrder(ResponseInterface $response): PaypalOrder
    {
        $status = $response->getStatusCode();
        $data = json_decode((string)$response->getBody(), true);
        if (($status !== 200 && $status !== 201) || !is_array($data)) {
            throw new PaypalApiException(sprintf('PayPal order creation returned HTTP %d.', $status), 1752600301);
        }

        return new PaypalOrder(
            (string)($data['id'] ?? ''),
            $this->approveUrl($data),
            (string)($data['status'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $data a capture or order-details response body
     */
    private function toCapture(array $data): PaypalCapture
    {
        $purchaseUnit = (array)($data['purchase_units'][0] ?? []);
        $capture = (array)($purchaseUnit['payments']['captures'][0] ?? []);
        $amount = (array)($capture['amount'] ?? []);

        return new PaypalCapture(
            (string)($capture['status'] ?? $data['status'] ?? ''),
            (string)($capture['id'] ?? ''),
            (string)($capture['custom_id'] ?? $purchaseUnit['custom_id'] ?? ''),
            isset($amount['value']) ? Money::fromDecimalString((string)$amount['value']) : null,
            (string)($amount['currency_code'] ?? ''),
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    private function approveUrl(array $data): string
    {
        foreach ((array)($data['links'] ?? []) as $link) {
            if (is_array($link) && in_array((string)($link['rel'] ?? ''), self::APPROVE_RELATIONS, true)) {
                return (string)($link['href'] ?? '');
            }
        }

        return '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hasIssue(array $data, string $issue): bool
    {
        foreach ((array)($data['details'] ?? []) as $detail) {
            if (is_array($detail) && (string)($detail['issue'] ?? '') === $issue) {
                return true;
            }
        }

        return false;
    }
}
