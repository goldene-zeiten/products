<?php

declare(strict_types=1);

use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

ExtensionUtility::registerPlugin(
    'Products',
    'ProductList',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.product_list',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'ProductDetail',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.product_detail',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'Basket',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.basket',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'Checkout',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.checkout',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'OrderHistory',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.order_history',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'Wishlist',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.wishlist',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'RecentlyViewed',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.recently_viewed',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'Search',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.search',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'CategoryNavigation',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.category_navigation',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'CategoryList',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.category_list',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionUtility::registerPlugin(
    'Products',
    'Withdrawal',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.withdrawal',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);
