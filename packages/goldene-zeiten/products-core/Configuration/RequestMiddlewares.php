<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Middleware\ExpressShippingQuoteMiddleware;
use GoldeneZeiten\Products\Core\Middleware\PaymentReturnMiddleware;
use GoldeneZeiten\Products\Core\Middleware\PaymentWebhookMiddleware;

/**
 * The payment callbacks and the express shipping-rate callback run after the site has been resolved - they
 * need the site's database context - but before page resolution, since their paths are not pages and must
 * never be routed as one. The gateway appends its own parameters to the return URL, so it cannot survive
 * cHash validation as a plugin action; the express callback is called by a wallet sheet with no session.
 */
return [
    'frontend' => [
        'goldene-zeiten/products/express-shipping-quote' => [
            'target' => ExpressShippingQuoteMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
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
