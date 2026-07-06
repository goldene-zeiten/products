<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Controller\Backend\ProductManagementModuleController;

return [
    'products' => [
        'labels' => [
            'title' => 'LLL:EXT:products/Resources/Private/Language/locallang_be.xlf:module.products.title',
        ],
        'iconIdentifier' => 'products-module',
    ],
    'products_management' => [
        'parent' => 'products',
        'position' => [],
        'access' => 'user',
        'workspaces' => 'live',
        'iconIdentifier' => 'products-module',
        'navigationComponent' => '@goldene-zeiten/products/backend/category-tree.js',
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
