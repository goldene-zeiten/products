<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionUtility::registerPlugin(
        'ProductsSearch',
        'Search',
        'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:plugin.search',
        'EXT:products_core/Resources/Public/Icons/Extension.svg'
    );

    $newColumnsArray = [
        'tx_products_search_browse_mode' => [
            'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.text',
                        'value' => 'text',
                    ],
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.firstletter',
                        'value' => 'firstletter',
                    ],
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.year',
                        'value' => 'year',
                    ],
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.field',
                        'value' => 'field',
                    ],
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.keyfield',
                        'value' => 'keyfield',
                    ],
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_browse_mode.lastentries',
                        'value' => 'lastentries',
                    ],
                ],
                'default' => 'text',
            ],
        ],
        'tx_products_search_target' => [
            'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_target',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_target.products',
                        'value' => 'products',
                    ],
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_target.articles',
                        'value' => 'articles',
                    ],
                    [
                        'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_target.categories',
                        'value' => 'categories',
                    ],
                ],
                'default' => 'products',
            ],
        ],
        'tx_products_search_field' => [
            'label' => 'LLL:EXT:products_search/Resources/Private/Language/locallang_be.xlf:tt_content.tx_products_search_field',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'default' => '',
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('tt_content', $newColumnsArray);

    ExtensionManagementUtility::addToAllTCAtypes(
        'tt_content',
        'tx_products_search_browse_mode, tx_products_search_target, tx_products_search_field',
        'productssearch_search',
    );
})();
