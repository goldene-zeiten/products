<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\Resource;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The guides and the facts are read as resources through the endpoint, in the language of the site and
 * rendered from templates a site can replace.
 */
final class GuideResourcesTest extends AbstractFrontendTestCase
{
    private const PROTOCOL_VERSION = '2025-06-18';

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
        'de' => [
            'id' => 0,
            'title' => 'Deutsch',
            'locale' => 'de_DE.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-payment-fixture',
        'goldene-zeiten/products-mcp',
    ];

    protected function siteSetDependencies(): array
    {
        return [...parent::siteSetDependencies(), 'goldene-zeiten/products-mcp'];
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->writeShopSite([]);
    }

    #[Test]
    public function theResourceListOffersTheGuidesAndTheFacts(): void
    {
        $sessionId = $this->openSession();

        $result = $this->resultOf($this->post(['jsonrpc' => '2.0', 'id' => 2, 'method' => 'resources/list'], $sessionId));

        $uris = array_column($result['resources'], 'uri');
        sort($uris);
        $this->assertSame(
            ['shop://guide/checkout', 'shop://guide/ordering', 'shop://guide/search', 'shop://shop/facts'],
            $uris
        );
        $this->assertSame('text/markdown', $result['resources'][0]['mimeType']);
    }

    #[Test]
    public function theCheckoutGuideNamesTheStepsPaymentMethodsAndLegalPagesInTheSiteLanguage(): void
    {
        $text = $this->read('shop://guide/checkout');

        $this->assertStringContainsString('Adresse', $text);
        $this->assertStringContainsString('Versandart', $text);
        $this->assertStringContainsString('Zahlungsart', $text);
        $this->assertStringContainsString('Declining Gateway Fixture Payment', $text);
        $this->assertStringContainsString('http://localhost/shop', $text);
    }

    #[Test]
    public function theFactsNameTheCurrencyAndTheTrader(): void
    {
        $text = $this->read('shop://shop/facts');

        $this->assertStringContainsString('GBP', $text);
        $this->assertStringContainsString('Muster GmbH', $text);
        $this->assertStringContainsString('DE123456789', $text);
    }

    #[Test]
    public function aSiteTemplateReplacesTheSearchGuide(): void
    {
        $this->writeShopSite([
            'mcp' => [
                'templateRootPaths' => [
                    'EXT:products_mcp/Resources/Private/Templates/',
                    'EXT:products_mcp/Tests/Functional/Fixtures/Templates/',
                ],
            ],
        ]);

        $this->assertSame("Custom search guide of the shop Demo Shop.\n", $this->read('shop://guide/search'));
    }

    /**
     * @param array<string, mixed> $mcpSettings
     */
    private function writeShopSite(array $mcpSettings): void
    {
        $products = [
            'mcp' => ['enabled' => true, ...($mcpSettings['mcp'] ?? [])],
            'pricing' => ['currency' => 'GBP'],
            'pids' => ['termsPage' => 2],
            'withdrawal' => ['trader' => ['name' => 'Muster GmbH']],
            'invoice' => ['vatId' => 'DE123456789'],
        ];
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'websiteTitle' => 'Demo Shop',
                'dependencies' => $this->siteSetDependencies(),
                'settings' => ['products' => $products],
            ]),
            [$this->buildDefaultLanguageConfiguration('de', '/')]
        );
    }

    private function read(string $uri): string
    {
        $sessionId = $this->openSession();
        $result = $this->resultOf($this->post([
            'jsonrpc' => '2.0',
            'id' => 2,
            'method' => 'resources/read',
            'params' => ['uri' => $uri],
        ], $sessionId));

        return $result['contents'][0]['text'];
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

    /**
     * @return array<string, mixed>
     */
    private function resultOf(ResponseInterface $response): array
    {
        $this->assertSame(200, $response->getStatusCode(), (string)$response->getBody());
        $payload = json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);

        return $payload['result'];
    }
}
