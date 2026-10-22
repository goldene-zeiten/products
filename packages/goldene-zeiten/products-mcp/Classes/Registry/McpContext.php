<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Registry;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * What a tool or resource gets to know about the request it answers: the site the client is connected to and
 * the request itself.
 */
final readonly class McpContext
{
    public function __construct(
        public Site $site,
        public ServerRequestInterface $request
    ) {}
}
