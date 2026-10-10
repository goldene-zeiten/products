<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Configuration;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Resolves the site a request belongs to, so a configuration factory can pick up the per-site overrides
 * for the request it is serving. Inspecting the request for the site it belongs to is an identity lookup,
 * not configuration state.
 *
 * Pass the request explicitly wherever the caller has it {@see fromRequest()}. {@see resolve()} falls back
 * to the globally published request, which core only sets once the page is being rendered - a middleware
 * running before that, such as a payment gateway callback, gets no site from it.
 */
final class CurrentSiteResolver
{
    public function resolve(): ?Site
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        return $request instanceof ServerRequestInterface ? $this->fromRequest($request) : null;
    }

    public function fromRequest(ServerRequestInterface $request): ?Site
    {
        $site = $request->getAttribute('site');

        return $site instanceof Site ? $site : null;
    }
}
