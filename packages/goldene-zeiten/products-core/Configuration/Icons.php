<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;
use TYPO3\CMS\Core\Information\Typo3Version;

$isTYPO3v14OrHigher = (new Typo3Version())->getMajorVersion() >= 14;

return [
    'products-module' => [
        'provider' => SvgIconProvider::class,
        'source' => $isTYPO3v14OrHigher
            ? 'EXT:products_core/Resources/Public/Icons/Extension-v14.svg'
            : 'EXT:products_core/Resources/Public/Icons/Extension.svg',
    ],
    'products-order' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:products_core/Resources/Public/Icons/Order.svg',
    ],
    'products-categories' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:products_core/Resources/Public/Icons/Categories.svg',
    ],
    'products-category' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:products_core/Resources/Public/Icons/Category.svg',
    ],
    'products-product' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:products_core/Resources/Public/Icons/Product.svg',
    ],
    'products-article' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:products_core/Resources/Public/Icons/Article.svg',
    ],
];
