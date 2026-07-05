<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_paymenttransaction',
        'label' => 'external_id',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [],
        'searchFields' => 'external_id,payment_method',
        'iconfile' => 'EXT:products/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'order_uid, payment_method, external_id, state, amount, currency, raw_data'],
    ],
    'columns' => [
        'order_uid' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_paymenttransaction.order_uid',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'payment_method' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_paymenttransaction.payment_method',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'external_id' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_paymenttransaction.external_id',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'state' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_paymenttransaction.state',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'amount' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_paymenttransaction.amount',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'currency' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_paymenttransaction.currency',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'readOnly' => true,
            ],
        ],
        'raw_data' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_paymenttransaction.raw_data',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
            ],
        ],
    ],
];
