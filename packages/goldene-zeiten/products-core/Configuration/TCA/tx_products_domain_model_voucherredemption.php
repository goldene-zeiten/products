<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucherredemption',
        'label' => 'voucher_code',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [],
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'voucher_uid, voucher_code, order_uid, frontend_user, discount_total, redeemed_at'],
    ],
    'columns' => [
        'voucher_uid' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucherredemption.voucher_uid',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'voucher_code' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucherredemption.voucher_code',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'order_uid' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucherredemption.order_uid',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'frontend_user' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucherredemption.frontend_user',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'discount_total' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucherredemption.discount_total',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'redeemed_at' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucherredemption.redeemed_at',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
    ],
];
