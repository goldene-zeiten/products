<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Domain\Enum\CreditPointsTransactionType;

return [
    'ctrl' => [
        'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_creditpointstransaction',
        'label' => 'frontend_user',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'iconfile' => 'EXT:products/Resources/Public/Icons/Extension.svg',
    ],
    'types' => [
        '1' => ['showitem' => 'frontend_user, order_uid, points, type, created'],
    ],
    'columns' => [
        'frontend_user' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_creditpointstransaction.frontend_user',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'order_uid' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_creditpointstransaction.order_uid',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'points' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_creditpointstransaction.points',
            'config' => [
                'type' => 'number',
                'readOnly' => true,
            ],
        ],
        'type' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_creditpointstransaction.type',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    [
                        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_creditpointstransaction.type.earn',
                        'value' => CreditPointsTransactionType::EARN->value,
                    ],
                    [
                        'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_creditpointstransaction.type.redeem',
                        'value' => CreditPointsTransactionType::REDEEM->value,
                    ],
                ],
                'readOnly' => true,
            ],
        ],
        'created' => [
            'label' => 'LLL:EXT:products/Resources/Private/Language/locallang_tca.xlf:tx_products_domain_model_creditpointstransaction.created',
            'config' => [
                'type' => 'datetime',
                'size' => 13,
                'eval' => 'datetime',
                'readOnly' => true,
            ],
        ],
    ],
];
