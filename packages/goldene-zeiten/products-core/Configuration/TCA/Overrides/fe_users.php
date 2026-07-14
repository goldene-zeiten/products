<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

(static function (): void {
    $newColumnsArray = [
        'tx_products_discount_percent' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_be.xlf:fe_users.tx_products_discount_percent',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'default' => '0.00',
            ],
        ],
    ];

    ExtensionManagementUtility::addTCAcolumns('fe_users', $newColumnsArray);
    ExtensionManagementUtility::addToAllTCAtypes(
        'fe_users',
        'tx_products_discount_percent',
        '',
        'after:usergroup',
    );
})();
