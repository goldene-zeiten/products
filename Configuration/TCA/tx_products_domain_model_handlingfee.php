<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Backend\Form\CountryItemsProcFunc;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_handlingfee',
        'label' => 'title',
        'label_alt' => 'country, rate',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'title, country, --palette--;;range, rate'],
    ],
    'palettes' => [
        'range' => [
            'showitem' => 'min_order_value, max_order_value, --linebreak--, min_weight, max_weight',
        ],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_handlingfee.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'country' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_handlingfee.country',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => CountryItemsProcFunc::class . '->getCountries',
            ],
        ],
        'min_order_value' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_handlingfee.min_order_value',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'max_order_value' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_handlingfee.max_order_value',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'min_weight' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_handlingfee.min_weight',
            'config' => [
                'type' => 'number',
                'size' => 10,
            ],
        ],
        'max_weight' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_handlingfee.max_weight',
            'config' => [
                'type' => 'number',
                'size' => 10,
            ],
        ],
        'rate' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_handlingfee.rate',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
    ],
];
