<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Http;

use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * A thin wrapper around TYPO3's {@see RequestFactory} that removes the request-building boilerplate every
 * carrier or gateway integration would otherwise repeat, and bounds the total request time so a stalled
 * third party cannot hang the synchronous checkout request. It deliberately returns the raw response and
 * never inspects the status code - mapping HTTP statuses to business meaning (a 400 that is really "no rate
 * available", a 422 that is a declined capture) is the caller's job.
 */
final class ApiHttpClient
{
    private const DEFAULT_TIMEOUT_SECONDS = 30.0;

    private const DEFAULT_CONNECT_TIMEOUT_SECONDS = 10.0;

    public function __construct(
        private readonly RequestFactory $requestFactory,
    ) {}

    /**
     * @param array<int|string, mixed> $body a JSON object (string keys) or, for APIs that take one, a list
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array $body, array $headers = []): ResponseInterface
    {
        return $this->send('POST', $url, json_encode($body, JSON_THROW_ON_ERROR), [
            'Content-Type' => 'application/json',
        ] + $headers);
    }

    /**
     * A JSON PATCH - a partial update of a resource. Amazon Pay's Update Checkout Session sends a JSON
     * object; PayPal's Orders v2 patch sends a JSON Patch list of operations - hence the mixed key type.
     *
     * @param array<int|string, mixed> $body a JSON object (string keys) or a JSON Patch list (int keys)
     * @param array<string, string> $headers
     */
    public function patchJson(string $url, array $body, array $headers = []): ResponseInterface
    {
        return $this->send('PATCH', $url, json_encode($body, JSON_THROW_ON_ERROR), [
            'Content-Type' => 'application/json',
        ] + $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function postForm(string $url, string $body, array $headers = []): ResponseInterface
    {
        return $this->send('POST', $url, $body, [
            'Content-Type' => 'application/x-www-form-urlencoded',
        ] + $headers);
    }

    /**
     * A POST with no request body - some gateway endpoints (e.g. capturing an already-described order)
     * take their whole input from the URL.
     *
     * @param array<string, string> $headers
     */
    public function post(string $url, array $headers = []): ResponseInterface
    {
        return $this->send('POST', $url, null, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    public function get(string $url, array $headers = []): ResponseInterface
    {
        return $this->send('GET', $url, null, $headers);
    }

    /**
     * @param array<string, string> $headers
     */
    private function send(string $method, string $url, ?string $body, array $headers): ResponseInterface
    {
        $options = [
            'headers' => ['Accept' => 'application/json'] + $headers,
            // The caller maps an error status to business meaning, so never let Guzzle raise on one.
            'http_errors' => false,
            'connect_timeout' => $this->boundedTimeout('connect_timeout', self::DEFAULT_CONNECT_TIMEOUT_SECONDS),
            'timeout' => $this->boundedTimeout('timeout', self::DEFAULT_TIMEOUT_SECONDS),
        ];
        if ($body !== null) {
            $options['body'] = $body;
        }

        try {
            return $this->requestFactory->request($url, $method, $options);
        } catch (GuzzleException $exception) {
            throw new ApiTransportException(
                sprintf('%s request to %s failed at transport level.', $method, $url),
                1752600000,
                $exception,
            );
        }
    }

    /**
     * A gateway or carrier call runs synchronously inside the checkout request, so it must never be able to
     * hang a PHP worker forever. Defer to an integrator's explicit HTTP timeout, but substitute a bounded
     * default whenever the global value is left deactivated (0) - which is TYPO3's own default for the total
     * `timeout`.
     */
    private function boundedTimeout(string $option, float $default): float
    {
        $configured = (float)($GLOBALS['TYPO3_CONF_VARS']['HTTP'][$option] ?? 0);

        return $configured > 0.0 ? $configured : $default;
    }
}
