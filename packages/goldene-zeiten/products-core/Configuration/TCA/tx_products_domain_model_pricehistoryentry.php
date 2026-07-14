<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_pricehistoryentry',
        'label' => 'price',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'readOnly' => true,
        'hideTable' => true,
        'adminOnly' => true,
    ],
    'types' => [
        '1' => ['showitem' => 'product, article, price, valid_from, valid_until, recorded_at'],
    ],
    'columns' => [
        'product' => [
            'label' => 'Product',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_product',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'article' => [
            'label' => 'Article',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_article',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'price' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_pricehistoryentry.price',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
                'readOnly' => true,
            ],
        ],
        'valid_from' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_pricehistoryentry.valid_from',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'valid_until' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_pricehistoryentry.valid_until',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'recorded_at' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_pricehistoryentry.recorded_at',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
    ],
];
