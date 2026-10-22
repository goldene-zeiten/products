<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Middleware;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * An MCP client talks JSON-RPC 2.0 over POST to the endpoint path of the site: it opens a session with
 * "initialize", then lists and calls the tools within that session.
 */
final class McpEndpointMiddlewareTest extends AbstractFrontendTestCase
{
    private const PROTOCOL_VERSION = '2025-06-18';

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-mcp',
    ];

    protected function siteSetDependencies(): array
    {
        return [...parent::siteSetDependencies(), 'goldene-zeiten/products-mcp'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeShopSite(true);
    }

    #[Test]
    public function initializeOpensASessionAndAnswersWithServerInfoAndCapabilities(): void
    {
        $response = $this->initialize();

        $this->assertSame(200, $response->getStatusCode());
        $result = $this->resultOf($response);
        $this->assertSame(self::PROTOCOL_VERSION, $result['protocolVersion']);
        $this->assertSame('Demo Shop', $result['serverInfo']['name']);
        $this->assertArrayHasKey('tools', $result['capabilities']);
        $sessionId = $response->getHeaderLine('Mcp-Session-Id');
        $this->assertNotSame('', $sessionId);
        $this->assertSame(1, $this->countSessionRows());
    }

    #[Test]
    public function theSessionListsAndCallsTheShopInfoTool(): void
    {
        $sessionId = $this->initialize()->getHeaderLine('Mcp-Session-Id');
        $this->post(['jsonrpc' => '2.0', 'method' => 'notifications/initialized'], $sessionId);

        $list = $this->resultOf($this->post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'], $sessionId));
        $this->assertContains('shop_info', array_column($list['tools'], 'name'));

        $call = $this->resultOf($this->post([
            'jsonrpc' => '2.0',
            'id' => 3,
            'method' => 'tools/call',
            'params' => ['name' => 'shop_info', 'arguments' => new \stdClass()],
        ], $sessionId));
        $shop = json_decode($call['content'][0]['text'], true);
        $this->assertSame('GBP', $shop['currency']);
        $this->assertSame('Demo Shop', $shop['name']);
        $this->assertSame('/', $shop['baseUrl']);
    }

    #[Test]
    public function aSiteWithoutHostInItsBaseAnswersOnTheHostTheRequestCameInOn(): void
    {
        $response = $this->initialize('http://shop.example.com/mcp');

        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $this->assertNotSame('', $response->getHeaderLine('Mcp-Session-Id'));
    }

    #[Test]
    public function aRequestWithAnUnknownSessionIsRejected(): void
    {
        $response = $this->post(
            ['jsonrpc' => '2.0', 'id' => 2, 'method' => 'tools/list'],
            '3b0b6d58-5b3f-4c2d-9d2a-7a1d4f7d8f01'
        );

        $this->assertSame(404, $response->getStatusCode());
    }

    #[Test]
    public function theEndpointIsLeftToThePageRenderingWhileItIsDisabled(): void
    {
        $this->writeShopSite(false);

        $response = $this->initialize();

        $this->assertSame(404, $response->getStatusCode());
        $this->assertStringNotContainsString('jsonrpc', (string)$response->getBody());
        $this->assertSame('', $response->getHeaderLine('Mcp-Session-Id'));
        $this->assertSame(0, $this->countSessionRows());
    }

    private function writeShopSite(bool $enabled): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'websiteTitle' => 'Demo Shop',
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'mcp' => ['enabled' => $enabled],
                        'pricing' => ['currency' => 'GBP'],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    private function initialize(string $url = 'http://localhost/mcp'): ResponseInterface
    {
        return $this->post([
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
            ],
        ], null, $url);
    }

    /**
     * An MCP request is a JSON POST that accepts both a JSON and an event-stream answer.
     *
     * @param array<string, mixed> $message
     */
    private function post(array $message, ?string $sessionId = null, string $url = 'http://localhost/mcp'): ResponseInterface
    {
        $request = (new InternalRequest($url))
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

    /**
     * @return array<string, mixed>
     */
    private function resultOf(ResponseInterface $response): array
    {
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        return $payload['result'];
    }

    private function countSessionRows(): int
    {
        return $this->getConnectionPool()->getConnectionForTable('tx_productsmcp_session')
            ->count('*', 'tx_productsmcp_session', []);
    }
}
