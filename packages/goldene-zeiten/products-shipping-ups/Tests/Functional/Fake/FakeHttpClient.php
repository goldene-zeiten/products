<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Fake;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\Stream;

/**
 * A PSR-18 client that returns queued responses instead of calling out, and records the requests it was
 * given, so the UPS HTTP integration can be exercised end-to-end without any network access.
 */
final class FakeHttpClient implements ClientInterface
{
    /**
     * @var array<int, ResponseInterface|ClientExceptionInterface>
     */
    private array $queue = [];

    /**
     * @var RequestInterface[]
     */
    public array $received = [];

    public function willReturn(ResponseInterface $response): void
    {
        $this->queue[] = $response;
    }

    public function willThrow(ClientExceptionInterface $exception): void
    {
        $this->queue[] = $exception;
    }

    public function sendRequest(RequestInterface $request): ResponseInterface
    {
        $this->received[] = $request;
        $next = array_shift($this->queue);
        if ($next === null) {
            throw new \RuntimeException('FakeHttpClient: no queued response for ' . $request->getUri(), 1752581000);
        }
        if ($next instanceof ClientExceptionInterface) {
            throw $next;
        }

        return $next;
    }

    public function lastRequest(): RequestInterface
    {
        return $this->received[array_key_last($this->received)];
    }

    /**
     * @param array<string, mixed> $data
     */
    public static function jsonResponse(int $status, array $data): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        $body->write(json_encode($data, JSON_THROW_ON_ERROR));
        $body->rewind();

        return new Response($body, $status, ['Content-Type' => 'application/json']);
    }

    public static function transportError(string $message): ClientExceptionInterface
    {
        return new class ($message) extends \RuntimeException implements ClientExceptionInterface {};
    }
}
