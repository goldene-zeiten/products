<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Search\Controller\SearchController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::configurePlugin(
        'ProductsSearch',
        'Search',
        [
            SearchController::class => 'search',
        ],
        [
            SearchController::class => 'search',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
})();
