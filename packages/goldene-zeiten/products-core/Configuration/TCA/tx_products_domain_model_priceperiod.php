<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_priceperiod',
        'label' => 'price',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'valid_from',
            'endtime' => 'valid_until',
        ],
        'hideTable' => true,
        'versioningWS' => true,
    ],
    'types' => [
        '1' => ['showitem' => 'price, --linebreak--, fe_group, --palette--;;validity, note'],
    ],
    'palettes' => [
        'validity' => [
            'showitem' => 'valid_from, valid_until',
        ],
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
        'fe_group' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_priceperiod.fe_group',
            'description' => 'Empty/"Public" = general advertised price reduction. A specific group = reseller/tier pricing, excluded from the public price-history ledger.',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_groups',
                'items' => [
                    [
                        'label' => 'Public (all visitors)',
                        'value' => 0,
                    ],
                ],
                'default' => 0,
            ],
        ],
        'price' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_priceperiod.price',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'valid_from' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_priceperiod.valid_from',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
            ],
        ],
        'valid_until' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_priceperiod.valid_until',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
                'range' => [
                    'upper' => mktime(0, 0, 0, 1, 1, 2038),
                ],
            ],
        ],
        'note' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_priceperiod.note',
            'description' => 'Editor-facing reason for this price period (e.g. "Summer sale", "Reseller tier B"). Not used by any logic.',
            'config' => [
                'type' => 'input',
                'size' => 40,
                'eval' => 'trim',
            ],
        ],
    ],
];
