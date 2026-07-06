<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order',
        'label' => 'order_number',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'enablecolumns' => [],
        'iconfile' => 'EXT:products/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'order_number, order_date, frontend_user, email, billing_address, delivery_address, payment_method, payment_status, status, invoice_number, currency, total_net, total_tax, total_gross, tax_country, tax_breakdown, status_log, items, customer_note, terms_accepted_at, site_identifier, legacy_order_data, legacy_country_name'],
    ],
    'columns' => [
        'order_number' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.order_number',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'readOnly' => true,
            ],
        ],
        'order_date' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.order_date',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
        'frontend_user' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.frontend_user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'default' => 0,
                'items' => [
                    ['label' => 'Guest', 'value' => 0],
                ],
                'readOnly' => true,
            ],
        ],
        'email' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.email',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim',
                'readOnly' => true,
            ],
        ],
        'billing_address' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.billing_address',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_orderaddress',
                'readOnly' => true,
            ],
        ],
        'delivery_address' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.delivery_address',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_orderaddress',
                'readOnly' => true,
            ],
        ],
        'payment_method' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.payment_method',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'payment_status' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.payment_status',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'status' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.status',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'invoice_number' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.invoice_number',
            'config' => [
                'type' => 'input',
                'size' => 30,
                'readOnly' => true,
            ],
        ],
        'currency' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.currency',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'readOnly' => true,
            ],
        ],
        'total_net' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.total_net',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'total_tax' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.total_tax',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'total_gross' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.total_gross',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'tax_country' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.tax_country',
            'config' => [
                'type' => 'input',
                'size' => 10,
                'readOnly' => true,
            ],
        ],
        'tax_breakdown' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.tax_breakdown',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
            ],
        ],
        'status_log' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.status_log',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
            ],
        ],
        'items' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.items',
            'config' => [
                'type' => 'inline',
                'foreign_table' => 'tx_products_domain_model_orderitem',
                'foreign_field' => 'parent_order',
                'readOnly' => true,
                'appearance' => [
                    'collapseAll' => 1,
                    'showNewRecordLink' => false,
                ],
            ],
        ],
        'customer_note' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.customer_note',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
            ],
        ],
        'terms_accepted_at' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.terms_accepted_at',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'readOnly' => true,
            ],
        ],
        'site_identifier' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.site_identifier',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
        'legacy_order_data' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.legacy_order_data',
            'config' => [
                'type' => 'text',
                'readOnly' => true,
            ],
        ],
        'legacy_country_name' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_order.legacy_country_name',
            'config' => [
                'type' => 'input',
                'readOnly' => true,
            ],
        ],
    ],
];
