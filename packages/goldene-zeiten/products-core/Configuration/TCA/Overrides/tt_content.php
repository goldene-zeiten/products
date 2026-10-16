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
        'WithdrawalInstructions',
        'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:plugin.withdrawal_instructions',
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
        'tx_products_navigation_style',
        'productscore_categorynavigation',
    );

    $newColumnsArray = [
        'tx_products_items_per_page' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_items_per_page',
            'description' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_items_per_page.description',
            'config' => [
                'type' => 'number',
                'range' => [
                    'lower' => 0,
                ],
                'default' => 0,
            ],
        ],
        'tx_products_pagination' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_pagination',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 1,
            ],
        ],
        'tx_products_sorting' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_sorting',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_sorting.manual',
                        'value' => 'manual',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_sorting.title',
                        'value' => 'title',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_sorting.price_asc',
                        'value' => 'price_asc',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_sorting.price_desc',
                        'value' => 'price_desc',
                    ],
                    [
                        'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_sorting.newest',
                        'value' => 'newest',
                    ],
                ],
                'default' => 'manual',
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumnsArray);

    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        '--palette--;LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:tt_content.palette.listing;productsListing',
        'productscore_productlist,productscore_categorylist',
    );
    $GLOBALS['TCA']['tt_content']['palettes']['productsListing'] = [
        'showitem' => 'tx_products_items_per_page, tx_products_pagination, tx_products_sorting',
    ];

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
