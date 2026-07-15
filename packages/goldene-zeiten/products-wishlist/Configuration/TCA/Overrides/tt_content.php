<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::registerPlugin(
        'ProductsWishlist',
        'Wishlist',
        'LLL:EXT:products_wishlist/Resources/Private/Language/locallang_be.xlf:plugin.wishlist',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );
})();
