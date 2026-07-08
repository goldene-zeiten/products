<?php

declare(strict_types=1);

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_wishlistitem',
        'label' => 'frontend_user',
        'label_alt' => 'product',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'sortby' => 'sorting',
        'iconfile' => 'EXT:products/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'frontend_user, product, created'],
    ],
    'columns' => [
        'frontend_user' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_wishlistitem.frontend_user',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'fe_users',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'product' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_wishlistitem.product',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'foreign_table' => 'tx_products_domain_model_product',
                'minitems' => 1,
                'maxitems' => 1,
            ],
        ],
        'created' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_wishlistitem.created',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'default' => 0,
            ],
        ],
        // No BE form field on purpose - ctrl.sortby already drives the physical column; this entry
        // only exists so Extbase's DataMapFactory maps it onto WishlistItem::$sorting at all
        // (a control-only column absent from `columns` is invisible to Extbase's persistence layer).
        'sorting' => [
            'config' => [
                'type' => 'passthrough',
            ],
        ],
    ],
];
