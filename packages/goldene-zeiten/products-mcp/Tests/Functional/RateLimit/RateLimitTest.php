<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Tests\Functional\RateLimit;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Http\StreamFactory;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * A client that calls the endpoint or opens checkout links faster than the site allows is answered with
 * "429 Too Many Requests" and a "Retry-After" header, each path counted against its own limit.
 */
final class RateLimitTest extends AbstractFrontendTestCase
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

    /**
     * The counters outlive a test, so each test starts with an empty rate limiter cache.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->get(CacheManager::class)->getCache('ratelimiter')->flush();
    }

    #[Test]
    public function theRequestOverTheLimitIsAnsweredWith429AndARetryAfter(): void
    {
        $this->writeShopSite(['rateLimit' => 2]);

        $withinLimit = [$this->initialize()->getStatusCode(), $this->initialize()->getStatusCode()];
        $response = $this->initialize();

        $this->assertSame([200, 200], $withinLimit);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(1, (int)$response->getHeaderLine('Retry-After'));
        $payload = json_decode((string)$response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('2.0', $payload['jsonrpc']);
        $this->assertNull($payload['id']);
        $this->assertIsInt($payload['error']['code']);
        $this->assertNotSame('', $payload['error']['message']);
    }

    #[Test]
    public function aLimitOfZeroDoesNotThrottle(): void
    {
        $this->writeShopSite(['rateLimit' => 0]);

        for ($i = 0; $i < 5; $i++) {
            $this->assertSame(200, $this->initialize()->getStatusCode());
        }
    }

    #[Test]
    public function checkoutLinksHaveALimitOfTheirOwn(): void
    {
        $this->writeShopSite(['rateLimit' => 0, 'checkoutRateLimit' => 1]);

        $this->assertNotSame(429, $this->openCheckoutLink()->getStatusCode());
        $response = $this->openCheckoutLink();

        $this->assertSame(429, $response->getStatusCode());
        $this->assertGreaterThanOrEqual(1, (int)$response->getHeaderLine('Retry-After'));
        $this->assertSame(200, $this->initialize()->getStatusCode());
    }

    #[Test]
    public function theEndpointLimitDoesNotCountCheckoutLinks(): void
    {
        $this->writeShopSite(['rateLimit' => 1, 'checkoutRateLimit' => 0]);

        $this->assertSame(200, $this->initialize()->getStatusCode());
        $this->assertNotSame(429, $this->openCheckoutLink()->getStatusCode());
    }

    /**
     * @param array<string, int> $limits
     */
    private function writeShopSite(array $limits): void
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'websiteTitle' => 'Demo Shop',
                'dependencies' => $this->siteSetDependencies(),
                'settings' => ['products' => ['mcp' => ['enabled' => true, ...$limits]]],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    private function openCheckoutLink(): ResponseInterface
    {
        return $this->executeFrontendSubRequest(new InternalRequest('http://localhost/mcp/checkout?token=invalid'));
    }

    private function initialize(): ResponseInterface
    {
        $message = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'initialize',
            'params' => [
                'protocolVersion' => self::PROTOCOL_VERSION,
                'capabilities' => new \stdClass(),
                'clientInfo' => ['name' => 'phpunit', 'version' => '1.0.0'],
            ],
        ];
        $request = (new InternalRequest('http://localhost/mcp'))
            ->withMethod('POST')
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Accept', 'application/json, text/event-stream')
            ->withBody((new StreamFactory())->createStream(json_encode($message, JSON_THROW_ON_ERROR)));

        return $this->executeFrontendSubRequest($request);
    }
}
