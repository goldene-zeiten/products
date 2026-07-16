<?php

use GoldeneZeiten\Products\Express\ApplePay\Controller\ExpressCheckoutController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    // The button renders the live basket; validate and confirm each drive a processor call, so none may be
    // cached. validate and confirm are additionally dispatched by their own typeNum PAGEs
    // (Configuration/TypoScript/setup.typoscript) so the Apple Pay JS can post to them and receive raw JSON.
    ExtensionUtility::configurePlugin(
        'ProductsExpressApplePay',
        'ExpressCheckout',
        [
            ExpressCheckoutController::class => 'button, validate, confirm',
        ],
        [
            ExpressCheckoutController::class => 'button, validate, confirm',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
})();
