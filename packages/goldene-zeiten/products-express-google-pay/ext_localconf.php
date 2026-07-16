<?php

use GoldeneZeiten\Products\Express\GooglePay\Controller\ExpressCheckoutController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    // The button renders the live basket and confirm authorizes a payment, so neither may be cached.
    // confirm is additionally dispatched by its own typeNum PAGE (Configuration/TypoScript/setup.typoscript)
    // so the Google Pay JS can post to it and receive the raw JSON thank-you URL.
    ExtensionUtility::configurePlugin(
        'ProductsExpressGooglePay',
        'ExpressCheckout',
        [
            ExpressCheckoutController::class => 'button, confirm',
        ],
        [
            ExpressCheckoutController::class => 'button, confirm',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
})();
