<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Controller\Backend\OrderManagementModuleController;
use GoldeneZeiten\Products\Controller\Backend\ProductManagementModuleController;

return [
    'products' => [
        'labels' => [
            'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:module.products.title',
        ],
        'iconIdentifier' => 'products-module',
    ],
    'products_order' => [
        'parent' => 'products',
        'position' => [],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'products-order',
        'labels' => [
            'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:module.products_order.title',
            'shortDescription' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:module.products_order.shortDescription',
        ],
        'routes' => [
            '_default' => [
                'target' => OrderManagementModuleController::class . '::mainAction',
            ],
        ],
    ],
    'products_management' => [
        'parent' => 'products',
        'position' => [],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'products-categories',
        'navigationComponent' => '@goldene-zeiten/products/backend/category-tree',
        'labels' => [
            'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:module.products_management.title',
            'shortDescription' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:module.products_management.shortDescription',
        ],
        'routes' => [
            '_default' => [
                'target' => ProductManagementModuleController::class . '::mainAction',
            ],
        ],
    ],
];
