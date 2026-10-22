<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Mcp\Middleware\CheckoutHandoffMiddleware;
use GoldeneZeiten\Products\Mcp\Middleware\McpEndpointMiddleware;

/**
 * The endpoint runs after the site has been resolved - it answers for that site's settings - but before page
 * resolution, since its path is not a page and must never be routed as one. The checkout link additionally
 * runs after frontend authentication, as it fills the basket in the visitor's frontend session.
 */
return [
    'frontend' => [
        'goldene-zeiten/products-mcp/checkout-handoff' => [
            'target' => CheckoutHandoffMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
                'typo3/cms-frontend/authentication',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
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
