<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Middleware;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Sdk\McpHttpHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Serves the Model Context Protocol on the path set in "products.mcp.path" of a site that enables
 * "products.mcp.enabled". Every other request is passed on.
 *
 * It is a middleware rather than a page because assistants are not browsers: the path is fixed, and the
 * answer is JSON for the protocol, not a rendered page.
 */
final readonly class McpEndpointMiddleware implements MiddlewareInterface
{
    public function __construct(
        private McpHttpHandler $httpHandler
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site || !$this->isEndpoint($site, $request)) {
            return $handler->handle($request);
        }

        return $this->httpHandler->handle(new McpContext($site, $request));
    }

    private function isEndpoint(Site $site, ServerRequestInterface $request): bool
    {
        $settings = $site->getSettings();
        if (!(bool)$settings->get('products.mcp.enabled', false)) {
            return false;
        }
        $path = '/' . trim((string)$settings->get('products.mcp.path', '/mcp'), '/');

        return rtrim($site->getBase()->getPath(), '/') . $path === rtrim($request->getUri()->getPath(), '/');
    }
}
