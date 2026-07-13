<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
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

ExtensionUtility::registerPlugin(
    'Products',
    'Download',
    'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:plugin.download',
    'EXT:products/Resources/Public/Icons/Extension.svg'
);

ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_products_list_mode' => [
        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.all', 'value' => 'all'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.offers', 'value' => 'offers'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.highlights', 'value' => 'highlights'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.new', 'value' => 'new'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.affordable', 'value' => 'affordable'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_list_mode.articles', 'value' => 'articles'],
            ],
            'default' => 'all',
        ],
    ],
    'tx_products_recentlyviewed_mode' => [
        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.recent', 'value' => 'recent'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.mostviewed', 'value' => 'mostviewed'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_recentlyviewed_mode.mostviewedglobal', 'value' => 'mostviewedglobal'],
            ],
            'default' => 'recent',
        ],
    ],
    'tx_products_navigation_style' => [
        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_navigation_style',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_navigation_style.menu', 'value' => 'menu'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_navigation_style.dropdown', 'value' => 'dropdown'],
            ],
            'default' => 'menu',
        ],
    ],
]);

ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_products_search_browse_mode' => [
        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.text', 'value' => 'text'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.firstletter', 'value' => 'firstletter'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.year', 'value' => 'year'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.field', 'value' => 'field'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.keyfield', 'value' => 'keyfield'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.lastentries', 'value' => 'lastentries'],
            ],
            'default' => 'text',
        ],
    ],
    'tx_products_search_target' => [
        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_target',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectSingle',
            'items' => [
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_target.products', 'value' => 'products'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_target.articles', 'value' => 'articles'],
                ['label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_target.categories', 'value' => 'categories'],
            ],
            'default' => 'products',
        ],
    ],
    'tx_products_search_field' => [
        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_field',
        'config' => [
            'type' => 'input',
            'size' => 20,
            'default' => '',
        ],
    ],
]);

ExtensionManagementUtility::addTCAcolumns('tt_content', [
    'tx_products_category' => [
        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_category',
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
    ],
]);

ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_products_list_mode', 'products_productlist');
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_products_recentlyviewed_mode', 'products_recentlyviewed');
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_products_navigation_style', 'products_categorynavigation');
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_products_search_browse_mode', 'products_search');
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_products_search_target', 'products_search');
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_products_search_field', 'products_search');

$GLOBALS['TCA']['tt_content']['types']['products_productlist']['columnsOverrides']['records']['config']['allowed'] = 'tx_products_domain_model_product';

ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'records', 'products_productlist');
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_products_category', 'products_productlist', 'after:records');
ExtensionManagementUtility::addToAllTCAtypes('tt_content', 'tx_products_category', 'products_categorylist');
