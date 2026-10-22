<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Tool;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Calls the tools of a shop with a catalogue through the MCP endpoint, like an assistant does: it opens a
 * session once and then sends "tools/call" requests within it.
 */
abstract class AbstractToolTestCase extends AbstractFrontendTestCase
{
    private const PROTOCOL_VERSION = '2025-06-18';

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-mcp',
    ];

    private string $sessionId = '';

    protected function siteSetDependencies(): array
    {
        return [...parent::siteSetDependencies(), 'goldene-zeiten/products-mcp'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/catalog.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'websiteTitle' => 'Demo Shop',
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'mcp' => ['enabled' => true],
                        'pids' => ['storageFolder' => 2, 'detailPage' => 2],
                        'shipping' => ['enabled' => true],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->sessionId = $this->openSession();
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array{isError: bool, data: array<string, mixed>, text: string}
     */
    protected function callTool(string $name, array $arguments = []): array
    {
        $response = $this->post([
            'jsonrpc' => '2.0',
            'id' => 10,
            'method' => 'tools/call',
            'params' => ['name' => $name, 'arguments' => $arguments === [] ? new \stdClass() : $arguments],
        ], $this->sessionId);
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('result', $payload, (string)$response->getBody());
        $result = $payload['result'];
        $text = (string)$result['content'][0]['text'];
        $data = $result['structuredContent'] ?? json_decode($text, true);

        return ['isError' => (bool)($result['isError'] ?? false), 'data' => is_array($data) ? $data : [], 'text' => $text];
    }

    private function openSession(): string
    {
        $response = $this->post([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
            ],
        ]);
        $sessionId = $response->getHeaderLine('Mcp-Session-Id');
        $this->post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId);

        return $sessionId;
    }

    /**
     * @param array<string, mixed> $message
     */
    private function post(array $message, ?string $sessionId = null): ResponseInterface
    {
        $request = (new InternalRequest('http://localhost/mcp'))
            ->withMethod('POST')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->withBody((new StreamFactory())->createStream(json_encode($message, JSON_THROW_ON_ERROR)));
        if ($sessionId !== null) {
            $request = $request
                ->withHeader('Mcp-Session-Id', $sessionId)
                ->withHeader('MCP-Protocol-Version', self::PROTOCOL_VERSION);
        }

        return $this->executeFrontendSubRequest($request);
    }
}
