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
use GoldeneZeiten\Products\Controller\WithdrawalController;
use GoldeneZeiten\Products\Hooks\CategoryMountAccessHook;
use GoldeneZeiten\Products\Hooks\PriceAuditHook;
use GoldeneZeiten\Products\PageTitle\ProductPageTitleProvider;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkRecordUpdateAccess'][] = CategoryMountAccessHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = CategoryMountAccessHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = CategoryMountAccessHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = PriceAuditHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = PriceAuditHook::class;

ExtensionManagementUtility::addTypoScriptSetup(
    'config.pageTitleProviders.products.provider = ' . ProductPageTitleProvider::class . '
config.pageTitleProviders.products.before = record'
);

$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = '^tx_products_productdetail[attributeValues]';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = '=tx_products_productdetail[selectedArticle]';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = '^tx_products_productdetail[__referrer]';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = '^tx_products_productdetail[__trustedProperties]';

ExtensionUtility::configurePlugin(
    'Products',
    'ProductList',
    [
        ProductController::class => 'list, listByAjax',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'ProductDetail',
    [
        ProductController::class => 'show',
    ],
    [
        ProductController::class => 'show',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Basket',
    [
        BasketController::class => 'show, add, update, remove, applyVoucher, removeVoucher',
    ],
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
    [
        OrderController::class => 'list, show',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Wishlist',
    [
        WishlistController::class => 'show, add, remove, moveUp, moveDown',
    ],
    [
        WishlistController::class => 'show, add, remove, moveUp, moveDown',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'RecentlyViewed',
    [
        RecentlyViewedController::class => 'list, mostViewed, myMostViewed',
    ],
    [
        RecentlyViewedController::class => 'list, myMostViewed',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Search',
    [
        SearchController::class => 'search',
    ],
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
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'CategoryList',
    [
        CategoryController::class => 'list',
    ],
    [],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Invoice',
    [
        InvoiceController::class => 'download',
    ],
    [
        InvoiceController::class => 'download',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);

ExtensionUtility::configurePlugin(
    'Products',
    'Withdrawal',
    [
        WithdrawalController::class => 'form, confirm',
    ],
    [
        WithdrawalController::class => 'form, confirm',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);
