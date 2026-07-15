<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [],
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'parent_order, --palette--;;productRef, --palette--;;titles, quantity, --palette--;;pricing, --palette--;;lineTotals, options'],
    ],
    'palettes' => [
        'productRef' => [
            'showitem' => 'product, article',
        ],
        'titles' => [
            'showitem' => 'title, article_title, item_number',
        ],
        'pricing' => [
            'showitem' => 'unit_price_net, unit_price_gross, tax_rate',
        ],
        'lineTotals' => [
            'showitem' => 'line_total_net, line_total_tax, line_total_gross, --linebreak--, deposit_total',
        ],
    ],
    'columns' => [
        'parent_order' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.parent_order',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_order',
                'readOnly' => true,
            ],
        ],
        'product' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.product',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_product',
                'readOnly' => true,
            ],
        ],
        'article' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.article',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_article',
                'readOnly' => true,
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'article_title' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.article_title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'item_number' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.item_number',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'quantity' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.quantity',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'unit_price_net' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.unit_price_net',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'unit_price_gross' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.unit_price_gross',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'tax_rate' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.tax_rate',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'readOnly' => true,
            ],
        ],
        'line_total_net' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.line_total_net',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'line_total_tax' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.line_total_tax',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'line_total_gross' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.line_total_gross',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'deposit_total' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.deposit_total',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'options' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_orderitem.options',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
            ],
        ],
    ],
];
