<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Middleware\PaymentWebhookMiddleware;

/**
 * The payment webhook runs after the site has been resolved - it needs the site's database context - but
 * before page resolution, since its path is not a page and must never be routed as one.
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
    ],
];
