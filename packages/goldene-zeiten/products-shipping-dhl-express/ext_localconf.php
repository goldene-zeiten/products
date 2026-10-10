<?php

use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') or die();

(static function (): void {
    // Live rates are reused for a few minutes (the lifetime is set per entry), so the repeated quotes of
    // one checkout do not each call DHL.
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['products_shipping_dhl_express_rates'] ??= [
        'frontend' => VariableFrontend::class,
        'backend' => Typo3DatabaseBackend::class,
        'groups' => [
            'system',
        ],
    ];
})();
