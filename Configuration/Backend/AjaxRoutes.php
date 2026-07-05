<?php

declare(strict_types=1);

use GoldeneZeiten\Products\Controller\Backend\CategoryTreeController;

return [
    'products_category_tree_data' => [
        'path' => '/products/category-tree/data',
        'methods' => ['GET'],
        'target' => CategoryTreeController::class . '::fetchDataAction',
        'inheritAccessFromModule' => 'products_management',
    ],
];
