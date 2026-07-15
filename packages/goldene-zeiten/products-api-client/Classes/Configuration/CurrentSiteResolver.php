<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Configuration;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Resolves the site of the current frontend request, so a configuration factory can pick up the
 * per-site overrides for the request it is serving without threading the request through every caller.
 * Inspecting the request for the site it belongs to is an identity lookup, not configuration state,
 * which is why it may read the global request here.
 */
final class CurrentSiteResolver
{
    public function resolve(): ?Site
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $site = $request instanceof ServerRequestInterface ? $request->getAttribute('site') : null;

        return $site instanceof Site ? $site : null;
    }
}
