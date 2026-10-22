<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Sdk;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use Mcp\Server\Transport\Http\Middleware\CorsMiddleware;
use Mcp\Server\Transport\Http\Middleware\DnsRebindingProtectionMiddleware;
use Mcp\Server\Transport\StreamableHttpTransport;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Log\LoggerInterface;

/**
 * Answers one request to the MCP endpoint through the SDK's Streamable HTTP transport.
 */
final readonly class McpHttpHandler
{
    private const LOCAL_HOSTS = ['localhost', '127.0.0.1', '[::1]'];

    public function __construct(
        private McpServerFactory $serverFactory,
        private SessionStoreFactory $sessionStoreFactory,
        private ResponseFactoryInterface $responseFactory,
        private StreamFactoryInterface $streamFactory,
        private LoggerInterface $logger
    ) {}

    public function handle(McpContext $context): ResponseInterface
    {
        $lifetime = (int)$context->site->getSettings()->get('products.mcp.sessionLifetime', 3600);
        $server = $this->serverFactory->create($context, $this->sessionStoreFactory->create($lifetime));
        $transport = new StreamableHttpTransport(
            $context->request,
            $this->responseFactory,
            $this->streamFactory,
            $this->logger,
            $this->middleware($context)
        );

        return $server->run($transport);
    }

    /**
     * @return list<CorsMiddleware|DnsRebindingProtectionMiddleware>
     */
    private function middleware(McpContext $context): array
    {
        $hosts = [...self::LOCAL_HOSTS, $context->site->getBase()->getHost()];

        return [
            new CorsMiddleware(),
            new DnsRebindingProtectionMiddleware($hosts, [], $this->responseFactory, $this->streamFactory),
        ];
    }
}
