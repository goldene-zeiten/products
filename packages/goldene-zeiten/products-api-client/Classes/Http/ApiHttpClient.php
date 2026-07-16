<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Http;

use GoldeneZeiten\Products\ApiClient\Exception\ApiTransportException;
use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Http\Stream;

/**
 * A thin wrapper around the PSR-18 client that removes the request-building boilerplate every carrier or
 * gateway integration would otherwise repeat: create a stream, wrap it in a {@see Request}, send it, and
 * translate a transport-level failure into a single exception type. It deliberately returns the raw
 * response and never inspects the status code - mapping HTTP statuses to business meaning (a 400 that is
 * really "no rate available", a 422 that is a declined capture) is the caller's job.
 */
final class ApiHttpClient
{
    public function __construct(
        private readonly ClientInterface $httpClient,
    ) {}

    /**
     * @param array<string, mixed> $body
     * @param array<string, string> $headers
     */
    public function postJson(string $url, array $body, array $headers = []): ResponseInterface
    {
        return $this->send('POST', $url, json_encode($body, JSON_THROW_ON_ERROR), [
            'Content-Type' => 'application/json',
        ] + $headers);
    }

    /**
     * A JSON PATCH - a partial update of a resource. Amazon Pay's Update Checkout Session, for one, is a
     * genuine HTTP PATCH rather than a POST sub-resource.
     *
     * @param array<string, mixed> $body
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
        $stream = new Stream('php://temp', 'rw');
        if ($body !== null) {
            $stream->write($body);
            $stream->rewind();
        }
        $request = new Request($url, $method, $stream, ['Accept' => 'application/json'] + $headers);

        try {
            return $this->httpClient->sendRequest($request);
        } catch (ClientExceptionInterface $exception) {
            throw new ApiTransportException(
                sprintf('%s request to %s failed at transport level.', $method, $url),
                1752600000,
                $exception,
            );
        }
    }
}
