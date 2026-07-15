<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Backend\Form\CountryItemsProcFunc;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxrate',
        'label' => 'rate',
        'label_alt' => 'tax_class, country',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'valid_from',
            'endtime' => 'valid_until',
        ],
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'tax_class, country, rate, valid_from, valid_until'],
    ],
    'columns' => [
        'tax_class' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxrate.tax_class',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_taxclass',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'country' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxrate.country',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'itemsProcFunc' => CountryItemsProcFunc::class . '->getCountries',
            ],
        ],
        'rate' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxrate.rate',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'valid_from' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxrate.valid_from',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
            ],
        ],
        'valid_until' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxrate.valid_until',
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
    ],
];
