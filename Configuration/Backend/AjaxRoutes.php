<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Core\Controller\Backend\CategoryTreeController;

return [
    'products_category_tree_configuration' => [
        'path' => '/products/category-tree/configuration',
        'methods' => ['GET'],
        'target' => CategoryTreeController::class . '::fetchConfigurationAction',
        'inheritAccessFromModule' => 'products_management',
    ],
    'products_category_tree_data' => [
        'path' => '/products/category-tree/data',
        'methods' => ['GET'],
        'target' => CategoryTreeController::class . '::fetchDataAction',
        'inheritAccessFromModule' => 'products_management',
    ],
    'products_category_tree_filter' => [
        'path' => '/products/category-tree/filter',
        'methods' => ['GET'],
        'target' => CategoryTreeController::class . '::filterDataAction',
        'inheritAccessFromModule' => 'products_management',
    ],
    'products_category_tree_rootline' => [
        'path' => '/products/category-tree/rootline',
        'methods' => ['GET'],
        'target' => CategoryTreeController::class . '::fetchRootlineAction',
        'inheritAccessFromModule' => 'products_management',
    ],
    'products_category_tree_reorder' => [
        'path' => '/products/category-tree/reorder',
        'methods' => ['POST'],
        'target' => CategoryTreeController::class . '::reorderAction',
        'inheritAccessFromModule' => 'products_management',
    ],
];
