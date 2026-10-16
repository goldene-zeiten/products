<?php

declare(strict_types=1);

use GoldeneZeiten\Products\BasketSummary\Middleware\BasketSummaryMiddleware;

/**
 * The basket summary needs the site (prices, currency) and the frontend user session (the basket), and its
 * path is not a page, so it answers before page resolution.
 */
return [
    'frontend' => [
        'goldene-zeiten/products-basket-summary/summary' => [
            'target' => BasketSummaryMiddleware::class,
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
