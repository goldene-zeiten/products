<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

$columns = [
    'tx_products_category_mounts' => [
        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:be_users.tx_products_category_mounts',
        'config' => [
            'type' => 'select',
            'renderType' => 'selectTree',
            'foreign_table' => 'tx_products_domain_model_category',
            'treeConfig' => [
                'parentField' => 'parent_category',
                'appearance' => [
                    'expandAll' => true,
                    'showHeader' => true,
                ],
            ],
            'size' => 10,
            'maxitems' => 99,
        ],
    ],
];

ExtensionManagementUtility::addTCAcolumns('be_users', $columns);
ExtensionManagementUtility::addToAllTCAtypes('be_users', 'tx_products_category_mounts', '', 'after:allowed_languages');
