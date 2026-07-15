<?php

use GoldeneZeiten\Products\Core\Controller\BasketController;
use GoldeneZeiten\Products\Core\Controller\CategoryController;
use GoldeneZeiten\Products\Core\Controller\CheckoutController;
use GoldeneZeiten\Products\Core\Controller\DownloadController;
use GoldeneZeiten\Products\Core\Controller\InvoiceController;
use GoldeneZeiten\Products\Core\Controller\OrderController;
use GoldeneZeiten\Products\Core\Controller\ProductController;
use GoldeneZeiten\Products\Core\Controller\WithdrawalController;
use GoldeneZeiten\Products\Core\Hooks\CategoryMountAccessHook;
use GoldeneZeiten\Products\Core\Hooks\PriceAuditHook;
use GoldeneZeiten\Products\Core\PageTitle\ProductPageTitleProvider;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['checkRecordUpdateAccess'][] = CategoryMountAccessHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = CategoryMountAccessHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = CategoryMountAccessHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processDatamapClass'][] = PriceAuditHook::class;
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['t3lib/class.t3lib_tcemain.php']['processCmdmapClass'][] = PriceAuditHook::class;

    ExtensionManagementUtility::addTypoScriptSetup(
        'config.pageTitleProviders.products.provider = ' . ProductPageTitleProvider::class . '
    config.pageTitleProviders.products.before = record'
    );

    ExtensionUtility::configurePlugin(
        'ProductsCore',
        'ProductList',
        [
            ProductController::class => 'list, listByAjax',
        ],
        [],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        'ProductsCore',
        'ProductDetail',
        [
            ProductController::class => 'show',
        ],
        [
            ProductController::class => 'show',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    // Add-ons whose UI belongs on the basket page (the voucher form, for one) register their controller
    // onto this plugin from their own ext_localconf via ExtensionUtility::registerControllerActions(),
    // because only a plugin actually placed on the page is dispatched - so the core stays unaware of them.
    ExtensionUtility::configurePlugin(
        'ProductsCore',
        'Basket',
        [
            BasketController::class => 'show, add, update, remove',
        ],
        [
            BasketController::class => 'show, add, update, remove',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        'ProductsCore',
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
        'ProductsCore',
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
        'ProductsCore',
        'CategoryNavigation',
        [
            CategoryController::class => 'navigation',
        ],
        [],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        'ProductsCore',
        'CategoryList',
        [
            CategoryController::class => 'list',
        ],
        [],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        'ProductsCore',
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
        'ProductsCore',
        'Withdrawal',
        [
            WithdrawalController::class => 'form, confirm',
        ],
        [
            WithdrawalController::class => 'form, confirm',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );

    ExtensionUtility::configurePlugin(
        'ProductsCore',
        'Download',
        [
            DownloadController::class => 'list',
        ],
        [
            DownloadController::class => 'list',
        ],
        ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT
    );
})();
