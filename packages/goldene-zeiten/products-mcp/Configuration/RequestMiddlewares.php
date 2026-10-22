<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Mcp\Middleware\McpEndpointMiddleware;

/**
 * The endpoint runs after the site has been resolved - it answers for that site's settings - but before page
 * resolution, since its path is not a page and must never be routed as one.
 */
return [
    'frontend' => [
        'goldene-zeiten/products-mcp/endpoint' => [
            'target' => McpEndpointMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
