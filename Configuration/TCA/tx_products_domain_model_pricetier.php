<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_pricetier',
        'label' => 'min_quantity',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'hideTable' => true,
    ],
    'types' => [
        '1' => ['showitem' => 'min_quantity, price'],
    ],
    'columns' => [
        'product' => [
            'label' => 'Product',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_product',
                'default' => 0,
            ],
        ],
        'article' => [
            'label' => 'Article',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_article',
                'default' => 0,
            ],
        ],
        'min_quantity' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_pricetier.min_quantity',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 1,
                'range' => ['lower' => 1],
            ],
        ],
        'price' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_pricetier.price',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
    ],
];
