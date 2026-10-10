<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Wishlist\Controller\WishlistController;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::configurePlugin(
        'ProductsWishlist',
        'Wishlist',
        [
            WishlistController::class => 'show, add, remove, moveUp, moveDown',
        ],
        [
            WishlistController::class => 'show, add, remove, moveUp, moveDown',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    // The add/remove toggle the catalogue renders per product through lib.productsWishlistToggle. Not a
    // selectable content element: it is never registered in TCA.
    ExtensionUtility::configurePlugin(
        'ProductsWishlist',
        'WishlistToggle',
        [
            WishlistController::class => 'toggle',
        ],
        [
            WishlistController::class => 'toggle',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
})();
