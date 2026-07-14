<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Backend\Form\ProductListModeItemsProvider;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'ProductList',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.product_list',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'ProductDetail',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.product_detail',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'Basket',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.basket',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'Checkout',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.checkout',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'OrderHistory',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.order_history',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'Wishlist',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.wishlist',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'RecentlyViewed',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.recently_viewed',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'CategoryNavigation',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.category_navigation',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'CategoryList',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.category_list',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'Withdrawal',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.withdrawal',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    ExtensionUtility::registerPlugin(
        'ProductsCore',
        'Download',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.download',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    $newColumnsArray = [
        'tx_products_list_mode' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                // The built-in listings; a feature can register more, which itemsProcFunc appends.
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.all',
                        'value' => 'all',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.offers',
                        'value' => 'offers',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.highlights',
                        'value' => 'highlights',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.new',
                        'value' => 'new',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.articles',
                        'value' => 'articles',
                    ],
                ],
                'itemsProcFunc' => ProductListModeItemsProvider::class . '->populate',
                'default' => 'all',
            ],
        ],
        'tx_products_recentlyviewed_mode' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.recent',
                        'value' => 'recent',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.mostviewed',
                        'value' => 'mostviewed',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.mostviewedglobal',
                        'value' => 'mostviewedglobal',
                    ],
                ],
                'default' => 'recent',
            ],
        ],
        'tx_products_navigation_style' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_navigation_style',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_navigation_style.menu',
                        'value' => 'menu',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_navigation_style.dropdown',
                        'value' => 'dropdown',
                    ],
                ],
                'default' => 'menu',
            ],
        ], ];

    ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumnsArray);

    $newColumnsArray = [
        'tx_products_category' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_category',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectTree',
                'foreign_table' => 'tx_products_domain_model_category',
                'treeConfig' => [
                    'parentField' => 'parent_category',
                    'appearance' => [
                        'showHeader' => true,
                    ],
                ],
                'size' => 8,
                'maxitems' => 20,
                'minitems' => 0,
                'default' => '',
            ],
        ], ];

    ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumnsArray);

    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'tx_products_list_mode',
        'productscore_productlist',
    );
    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'tx_products_recentlyviewed_mode',
        'productscore_recentlyviewed',
    );
    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'tx_products_navigation_style',
        'productscore_categorynavigation',
    );

    $GLOBALS['TCA']['tt_content']['types']['productscore_productlist']['columnsOverrides']['records']['config']['allowed'] = 'tx_products_domain_model_product';

    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'records, tx_products_category',
        'productscore_productlist',
    );
    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'tx_products_category',
        'productscore_categorylist',
    );
})();
