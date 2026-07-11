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
use GoldeneZeiten\Products\PageTitle\ProductPageTitleProvider;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkRecordUpdateAccess'][] = CategoryMountAccessHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = CategoryMountAccessHook::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = CategoryMountAccessHook::class;

// Page-title providers are registered via `config.pageTitleProviders` TypoScript, not a DI tag -
// this mirrors exactly how EXT:core itself registers its own default `record` provider. "before"
// ensures ours is tried first; returning '' (no current product) correctly falls through to it.
ExtensionManagementUtility::addTypoScriptSetup(
    'config.pageTitleProviders.products.provider = ' . ProductPageTitleProvider::class . '
config.pageTitleProviders.products.before = record'
);

// The variant chooser's GET form resubmits with a combination unknown at render time (no
// pre-computable cHash is possible for it), and ProductController::showAction is already
// re-rendered fresh on every request (registered as a non-cacheable action) regardless of the
// surrounding page's own cache state - so requiring a cHash proof for these two parameters
// specifically would only ever break the feature, never protect anything real.
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = '^tx_products_productdetail[attributeValues]';
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'][] = '=tx_products_productdetail[selectedArticle]';

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
    // "show" now accepts a dynamic attributeValues/selectedArticle combination the variant
    // chooser's GET form resubmits with - a cacheable action can't validate a cHash for a
    // combination unknown at render time, so this can no longer be cacheable.
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
        WishlistController::class => 'show, add, remove, moveUp, moveDown',
    ],
    // non-cacheable actions
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
    // non-cacheable actions (myMostViewed is per-shopper, list/mostViewed are cacheable)
    [
        RecentlyViewedController::class => 'myMostViewed',
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

ExtensionUtility::configurePlugin(
    'Products',
    'Withdrawal',
    [
        WithdrawalController::class => 'form, confirm',
    ],
    // non-cacheable actions
    [
        WithdrawalController::class => 'form, confirm',
    ],
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
);
