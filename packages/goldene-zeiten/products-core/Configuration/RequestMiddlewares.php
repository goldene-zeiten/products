<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Middleware\ExpressShippingQuoteMiddleware;
use GoldeneZeiten\Products\Core\Middleware\PaymentReturnMiddleware;
use GoldeneZeiten\Products\Core\Middleware\PaymentWebhookMiddleware;

/**
 * The payment callbacks and the express shipping-rate callback run after the site has been resolved - they
 * need the site's database context - but before page resolution, since their paths are not pages and must
 * never be routed as one. The gateway appends its own parameters to the return URL, so it cannot survive
 * cHash validation as a plugin action. The payment return and the express callback additionally run after
 * frontend authentication: the return clears the customer's basket and checkout session and leaves a message
 * for the checkout, and the express callback quotes the customer's own basket, all of which live in the
 * frontend user session.
 */
return [
    'frontend' => [
        'goldene-zeiten/products/express-shipping-quote' => [
            'target' => ExpressShippingQuoteMiddleware::class,
            'after' => [
                'typo3/cms-frontend/site',
                'typo3/cms-frontend/authentication',
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
                'typo3/cms-frontend/authentication',
            ],
            'before' => [
                'typo3/cms-frontend/page-resolver',
            ],
        ],
    ],
];
