<?php

use GoldeneZeiten\Products\Express\Stripe\Controller\ExpressCheckoutController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    // The button renders the live basket and the confirm settles a payment, so neither action may be
    // cached - both are registered as uncached actions. Confirm is additionally dispatched by its own
    // typeNum PAGE (see Configuration/TypoScript/setup.typoscript) so the wallet JS can post to it and
    // receive the raw JSON thank-you URL rather than a page-embedded fragment.
    ExtensionUtility::configurePlugin(
        'ProductsExpressStripe',
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
