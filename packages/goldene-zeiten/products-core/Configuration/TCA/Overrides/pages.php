<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Page\PageTypes;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

(static function (): void {
    ExtensionManagementUtility::addTcaSelectItem(
        'pages',
        'doktype',
        [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:pages.doktype.withdrawal',
            'value' => PageTypes::DOKTYPE_WITHDRAWAL,
            'icon' => 'products-page-withdrawal',
            'group' => 'special',
        ],
        '1',
        'after'
    );

    $GLOBALS['TCA']['pages']['ctrl']['typeicon_classes'][PageTypes::DOKTYPE_WITHDRAWAL] = 'products-page-withdrawal';
    $GLOBALS['TCA']['pages']['types'][PageTypes::DOKTYPE_WITHDRAWAL] = $GLOBALS['TCA']['pages']['types'][1];

    $newColumnsArray = [
        'tx_products_nav_class' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:pages.tx_products_nav_class',
            'description' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:pages.tx_products_nav_class.description',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'max' => 255,
                'default' => '',
            ],
        ],
    ];
    ExtensionManagementUtility::addTCAcolumns('pages', $newColumnsArray);
    ExtensionManagementUtility::addToAllTCAtypes(
        'pages',
        'tx_products_nav_class',
        (string)PageTypes::DOKTYPE_WITHDRAWAL,
        'after:nav_title'
    );
})();
