<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Middleware;

use GoldeneZeiten\Products\Mcp\RateLimit\McpRateLimiter;
use GoldeneZeiten\Products\Mcp\RateLimit\RateLimitScope;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Answers "429 Too Many Requests" to a client IP that calls the MCP endpoint or opens checkout links more often
 * per minute than "products.mcp.rateLimit" and "products.mcp.checkoutRateLimit" allow. The body is a JSON-RPC
 * error and "Retry-After" tells the client when to try again. Requests to other paths are passed on untouched.
 */
final readonly class McpRateLimitMiddleware implements MiddlewareInterface
{
    private const JSON_RPC_SERVER_ERROR = -32000;

    public function __construct(
        private EndpointPaths $paths,
        private McpRateLimiter $rateLimiter,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        $scope = $site instanceof Site ? $this->scopeOf($site, $request) : null;
        if ($site instanceof Site && $scope !== null) {
            $retryAfter = $this->rateLimiter->consume($site, $request, $scope);
            if ($retryAfter !== null) {
                return $this->tooManyRequests($retryAfter);
            }
        }

        return $handler->handle($request);
    }

    private function scopeOf(Site $site, ServerRequestInterface $request): ?RateLimitScope
    {
        return match (true) {
            $this->paths->isEndpoint($site, $request) => RateLimitScope::Endpoint,
            $this->paths->isCheckout($site, $request) => RateLimitScope::Checkout,
            default => null,
        };
    }

    private function tooManyRequests(int $retryAfter): ResponseInterface
    {
        $body = json_encode([
            'jsonrpc' => '2.0',
            'id' => null,
            'error' => ['code' => self::JSON_RPC_SERVER_ERROR, 'message' => 'Too many requests, retry later.'],
        ], JSON_THROW_ON_ERROR);

        return $this->responseFactory->createResponse(429)
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Retry-After', (string)$retryAfter)
            ->withHeader('Cache-Control', 'no-store')
            ->withBody($this->streamFactory->createStream($body));
    }
}
