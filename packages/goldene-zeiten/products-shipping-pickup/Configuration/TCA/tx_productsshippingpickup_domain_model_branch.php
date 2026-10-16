<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang_tca.xlf:tx_productsshippingpickup_domain_model_branch',
        'label' => 'title',
        'label_alt' => 'city',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'languageField' => 'sys_language_uid',
        'transOrigPointerField' => 'l10n_parent',
        'transOrigDiffSourceField' => 'l10n_diffsource',
        'enablecolumns' => [
            'disabled' => 'hidden',
        ],
        'iconfile' => 'EXT:products_shipping_pickup/Resources/Public/Icons/Branch.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'title, --palette--;;address, opening_hours, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:language, sys_language_uid, l10n_parent, l10n_diffsource, --div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:access, hidden'],
    ],
    'palettes' => [
        'address' => [
            'showitem' => 'street, --linebreak--, zip, city, --linebreak--, country',
        ],
    ],
    'columns' => [
        'title' => [
            'label' => 'LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang_tca.xlf:tx_productsshippingpickup_domain_model_branch.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'required' => true,
            ],
        ],
        'street' => [
            'label' => 'LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang_tca.xlf:tx_productsshippingpickup_domain_model_branch.street',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'zip' => [
            'label' => 'LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang_tca.xlf:tx_productsshippingpickup_domain_model_branch.zip',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'city' => [
            'label' => 'LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang_tca.xlf:tx_productsshippingpickup_domain_model_branch.city',
            'l10n_mode' => 'exclude',
            'config' => [
                'type' => 'input',
                'size' => 20,
                'eval' => 'trim',
            ],
        ],
        'country' => [
            'label' => 'LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang_tca.xlf:tx_productsshippingpickup_domain_model_branch.country',
            'l10n_mode' => 'exclude',
            'description' => 'LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang_tca.xlf:tx_productsshippingpickup_domain_model_branch.country.description',
            'config' => [
                'type' => 'input',
                'size' => 2,
                'max' => 2,
                'eval' => 'trim,upper',
                'default' => 'DE',
            ],
        ],
        'opening_hours' => [
            'label' => 'LLL:EXT:products_shipping_pickup/Resources/Private/Language/locallang_tca.xlf:tx_productsshippingpickup_domain_model_branch.opening_hours',
            'config' => [
                'type' => 'text',
                'rows' => 4,
                'cols' => 40,
            ],
        ],
    ],
];
