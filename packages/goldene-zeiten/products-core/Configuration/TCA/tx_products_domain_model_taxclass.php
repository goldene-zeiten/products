<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxclass',
        'label' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'versioningWS' => true,
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'title, code'],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxclass.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'code' => [
            'label' => 'LLL:EXT:products_core/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_taxclass.code',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
    ],
];
