<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Voucher\Domain\Enum\VoucherDiscountType;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher',
        'label' => 'code',
        'label_alt' => 'title',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [
            'disabled' => 'hidden',
            'starttime' => 'valid_from',
            'endtime' => 'valid_until',
        ],
        'iconfile' => 'EXT:products_core/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => '--palette--;;identity, --palette--;;discount, --palette--;;limits, --palette--;;validity, --palette--;;flags, bound_frontend_user, generated_from_order'],
    ],
    'palettes' => [
        'identity' => [
            'showitem' => 'code, title',
        ],
        'discount' => [
            'showitem' => 'discount_type, discount_value',
        ],
        'limits' => [
            'showitem' => 'usage_limit, redemption_count, min_basket_value',
        ],
        'validity' => [
            'showitem' => 'valid_from, valid_until',
        ],
        'flags' => [
            'showitem' => 'combinable, waives_shipping_cost',
        ],
    ],
    'columns' => [
        'code' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.code',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,unique',
                'required' => true,
            ],
        ],
        'title' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.title',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
            ],
        ],
        'discount_type' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.discount_type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.discount_type.percentage',
                        'value' => VoucherDiscountType::PERCENTAGE->value,
                    ],
                    [
                        'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.discount_type.fixed',
                        'value' => VoucherDiscountType::FIXED->value,
                    ],
                ],
            ],
        ],
        'discount_value' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.discount_value',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'combinable' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.combinable',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'waives_shipping_cost' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.waives_shipping_cost',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'default' => 0,
            ],
        ],
        'usage_limit' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.usage_limit',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
            ],
        ],
        'redemption_count' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.redemption_count',
            'config' => [
                'type' => 'number',
                'size' => 10,
                'default' => 0,
                'readOnly' => true,
            ],
        ],
        'min_basket_value' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.min_basket_value',
            'config' => [
                'type' => 'number',
                'format' => 'decimal',
                'size' => 10,
                'eval' => 'trim',
            ],
        ],
        'bound_frontend_user' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.bound_frontend_user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'default' => 0,
            ],
        ],
        'valid_from' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.valid_from',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
            ],
        ],
        'valid_until' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.valid_until',
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
        'generated_from_order' => [
            'label' => 'LLL:EXT:products_voucher/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_voucher.generated_from_order',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_order',
                'default' => 0,
                'readOnly' => true,
            ],
        ],
    ],
];
