<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * The fixed paths the MCP package answers on a site: the endpoint at "products.mcp.path" and, below it, the
 * address a checkout link opens. Nothing is answered while "products.mcp.enabled" is off.
 */
final readonly class EndpointPaths
{
    private const CHECKOUT_SEGMENT = '/checkout';

    public function isEndpoint(Site $site, ServerRequestInterface $request): bool
    {
        return $this->matches($site, $request, '');
    }

    public function isCheckout(Site $site, ServerRequestInterface $request): bool
    {
        return $this->matches($site, $request, self::CHECKOUT_SEGMENT);
    }

    /**
     * The absolute address of a checkout link. A site base without a host, such as "/", takes the scheme and
     * host the request came in on.
     */
    public function checkoutUrl(Site $site, ServerRequestInterface $request, string $linkToken): string
    {
        $base = $site->getBase();
        $origin = $base->getHost() !== ''
            ? rtrim((string)$base->withPath('')->withQuery('')->withFragment(''), '/')
            : rtrim((string)$request->getUri()->withPath('')->withQuery('')->withFragment(''), '/');

        return $origin . rtrim($base->getPath(), '/') . $this->endpointPath($site) . self::CHECKOUT_SEGMENT
            . '?' . http_build_query(['token' => $linkToken]);
    }

    private function matches(Site $site, ServerRequestInterface $request, string $suffix): bool
    {
        if (!(bool)$site->getSettings()->get('products.mcp.enabled', false)) {
            return false;
        }

        return rtrim($site->getBase()->getPath(), '/') . $this->endpointPath($site) . $suffix === rtrim($request->getUri()->getPath(), '/');
    }

    private function endpointPath(Site $site): string
    {
        return '/' . trim((string)$site->getSettings()->get('products.mcp.path', '/mcp'), '/');
    }
}
