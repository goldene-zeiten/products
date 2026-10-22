<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Guide;

use GoldeneZeiten\Products\Mcp\Registry\McpContext;
use Psr\Http\Message\UriInterface;
use TYPO3\CMS\Core\Routing\RouterInterface;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;

/**
 * The absolute URLs a guide links to: a page of the site by its uid, and the base of the site.
 */
final class PageUrlResolver
{
    /**
     * Empty when no page is configured or the page cannot be linked.
     */
    public function resolve(McpContext $context, int $pageUid): string
    {
        if ($pageUid <= 0) {
            return '';
        }
        $language = $context->request->getAttribute('language');
        $arguments = $language instanceof SiteLanguage ? ['_language' => $language] : [];
        try {
            $uri = $context->site->getRouter()->generateUri($pageUid, $arguments, '', RouterInterface::ABSOLUTE_URL);
        } catch (\Throwable) {
            return '';
        }

        return (string)$this->withRequestHost($uri, $context);
    }

    public function base(McpContext $context): string
    {
        return (string)$this->withRequestHost($context->site->getBase(), $context);
    }

    /**
     * A site whose base is only a path is reached under the host of the request.
     */
    private function withRequestHost(UriInterface $uri, McpContext $context): UriInterface
    {
        if ($uri->getHost() !== '') {
            return $uri;
        }
        $requestUri = $context->request->getUri();

        return $uri
            ->withScheme($requestUri->getScheme())
            ->withHost($requestUri->getHost())
            ->withPort($requestUri->getPort());
    }
}
