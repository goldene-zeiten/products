<?php

declare(strict_types=1);

use GoldeneZeiten\Products\BasketSummary\Controller\BasketSummaryController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::configurePlugin(
        'ProductsBasketSummary',
        'Summary',
        [
            BasketSummaryController::class => 'summary',
        ],
        [
            BasketSummaryController::class => 'summary',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
})();
