<?php

declare(strict_types=1);

return [
    'dependencies' => ['backend'],
    'tags' => ['backend.module'],
    'imports' => [
        '@goldene-zeiten/products/' => 'EXT:products_core/Resources/Public/JavaScript/',
    ],
];
