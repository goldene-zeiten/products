<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Middleware\PaymentReturnMiddleware;
use GoldeneZeiten\Products\Core\Middleware\PaymentWebhookMiddleware;

/**
 * The payment callbacks run after the site has been resolved - they need the site's database context - but
 * before page resolution, since their paths are not pages and must never be routed as one. The gateway
 * appends its own parameters to the return URL, so it cannot survive cHash validation as a plugin action.
 */
return [
    'frontend' => [
        'goldene-zeiten/products/payment-webhook' => [
            'target' => PaymentWebhookMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
        'goldene-zeiten/products/payment-return' => [
            'target' => PaymentReturnMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
