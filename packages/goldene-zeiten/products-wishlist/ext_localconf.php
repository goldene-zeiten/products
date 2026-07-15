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
})();
