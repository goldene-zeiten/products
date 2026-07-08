<?php

use GoldeneZeiten\Products\Controller\BasketController;
use GoldeneZeiten\Products\Controller\CategoryController;
use GoldeneZeiten\Products\Controller\CheckoutController;
use GoldeneZeiten\Products\Controller\InvoiceController;
use GoldeneZeiten\Products\Controller\OrderController;
use GoldeneZeiten\Products\Controller\ProductController;
use GoldeneZeiten\Products\Controller\RecentlyViewedController;
use GoldeneZeiten\Products\Controller\SearchController;
use GoldeneZeiten\Products\Controller\WishlistController;
use GoldeneZeiten\Products\Hooks\CategoryMountAccessHook;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkRecordUpdateAccess'][] = CategoryMountAccessHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = CategoryMountAccessHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = CategoryMountAccessHook::class;

ExtensionUtility::configurePlugin(
    'Products',
    'ProductList',
    [
        ProductController::class => 'list, listByAjax',
    ],
    // non-cacheable actions
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'ProductDetail',
    [
        ProductController::class => 'show',
    ],
    // non-cacheable actions
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Basket',
    [
        BasketController::class => 'show, add, update, remove, applyVoucher, removeVoucher',
    ],
    // non-cacheable actions
    [
        BasketController::class => 'show, add, update, remove, applyVoucher, removeVoucher',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Checkout',
    [
        CheckoutController::class => 'address, submitAddress, shippingMethod, submitShippingMethod, payment, submitPayment, review, finalize, paymentReturn, paymentCancel, thankYou',
    ],
    // non-cacheable actions
    [
        CheckoutController::class => 'address, submitAddress, shippingMethod, submitShippingMethod, payment, submitPayment, review, finalize, paymentReturn, paymentCancel, thankYou',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'OrderHistory',
    [
        OrderController::class => 'list, show',
    ],
    // non-cacheable actions
    [
        OrderController::class => 'list, show',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Wishlist',
    [
        WishlistController::class => 'show, add, remove',
    ],
    // non-cacheable actions
    [
        WishlistController::class => 'show, add, remove',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'RecentlyViewed',
    [
        RecentlyViewedController::class => 'list',
    ],
    // non-cacheable actions
    [
        RecentlyViewedController::class => 'list',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Search',
    [
        SearchController::class => 'search',
    ],
    // non-cacheable actions
    [
        SearchController::class => 'search',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'CategoryNavigation',
    [
        CategoryController::class => 'navigation',
    ],
    // non-cacheable actions - none, the tree has no session/user dependency
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'CategoryList',
    [
        CategoryController::class => 'list',
    ],
    // non-cacheable actions - none, the listing has no session/user dependency
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Invoice',
    [
        InvoiceController::class => 'download',
    ],
    // non-cacheable actions
    [
        InvoiceController::class => 'download',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);
