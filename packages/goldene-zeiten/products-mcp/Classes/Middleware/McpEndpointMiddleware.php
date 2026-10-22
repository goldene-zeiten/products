<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Middleware;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use GoldeneZeiten\Products\Mcp\Sdk\McpHttpHandler;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\LanguageAspectFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * Serves the Model Context Protocol on the path set in "products.mcp.path" of a site that enables
 * "products.mcp.enabled". Every other request is passed on.
 *
 * The tools answer in the language of the site language the request was resolved to, so the language is set on
 * the context here, which page rendering would only do after the page was resolved.
 *
 * It is a middleware rather than a page because assistants are not browsers: the path is fixed, and the
 * answer is JSON for the protocol, not a rendered page.
 */
final readonly class McpEndpointMiddleware implements MiddlewareInterface
{
    public function __construct(
        private McpHttpHandler $httpHandler,
        private Context $context
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $site = $request->getAttribute('site');
        if (!$site instanceof Site || !$this->isEndpoint($site, $request)) {
            return $handler->handle($request);
        }

        $language = $request->getAttribute('language');
        if ($language instanceof SiteLanguage) {
            $this->context->setAspect('language', LanguageAspectFactory::createFromSiteLanguage($language));
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
