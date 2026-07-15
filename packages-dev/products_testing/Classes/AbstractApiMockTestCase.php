<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Testing;

use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\Request;
use TYPO3\CMS\Core\Http\Stream;

/**
 * Base for tests that exercise a third-party integration over its real HTTP path against the shared
 * WireMock server. The mock's base URL is provided by the functional runner (runTests.sh starts WireMock
 * and passes MOCK_BASE_URL); a plain phpunit run without it skips these tests rather than failing.
 *
 * Behaviour is selected purely through the request - the stubs key on the request body or a header - so a
 * test never has to reach into the client. The WireMock container is shared across the whole functional
 * run, so this base resets the scenario state and the request journal per test; a subclass adds its own
 * setUp (flushing a token cache, say) by calling {@see setUp()} first. See Build/mocks.
 */
abstract class AbstractApiMockTestCase extends AbstractFunctionalTestCase
{
    protected string $mockRoot = '';

    protected function setUp(): void
    {
        parent::setUp();
        $mockBaseUrl = (string)getenv('MOCK_BASE_URL');
        if ($mockBaseUrl === '') {
            $this->markTestSkipped('MOCK_BASE_URL is not set; the WireMock mock is wired by runTests.sh -s functional.');
        }
        $this->mockRoot = $mockBaseUrl;
        $this->send('POST', $mockBaseUrl . '/__admin/scenarios/reset');
        $this->send('DELETE', $mockBaseUrl . '/__admin/requests');
    }

    protected function httpClient(): ClientInterface
    {
        return $this->get(ClientInterface::class);
    }

    /**
     * How many requests WireMock recorded for a path, from its journal - lets a test prove token caching
     * and retries without a request-counting client double.
     */
    protected function recordedRequests(string $urlPath, string $method = 'POST'): int
    {
        $response = $this->send('POST', $this->mockRoot . '/__admin/requests/count', ['method' => $method, 'urlPath' => $urlPath]);
        $data = json_decode((string)$response->getBody(), true);

        return (int)($data['count'] ?? 0);
    }

    /**
     * The requests WireMock recorded for a path, so a test can assert the outgoing payload.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function loggedRequests(string $urlPath, string $method = 'POST'): array
    {
        $response = $this->send('POST', $this->mockRoot . '/__admin/requests/find', ['method' => $method, 'urlPath' => $urlPath]);
        $data = json_decode((string)$response->getBody(), true);

        return is_array($data['requests'] ?? null) ? $data['requests'] : [];
    }

    /**
     * @param array<string, mixed>|null $json
     */
    protected function send(string $method, string $url, ?array $json = null): ResponseInterface
    {
        $body = new Stream('php://temp', 'rw');
        if ($json !== null) {
            $body->write(json_encode($json, JSON_THROW_ON_ERROR));
            $body->rewind();
        }

        return $this->httpClient()->sendRequest(new Request($url, $method, $body, ['Content-Type' => 'application/json']));
    }
}
