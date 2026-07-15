<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Watermark',
    'description' => 'Automatic watermarking of product, article and category images for the Products shop system',
    'category' => 'fe',
    'author' => 'Markus Hofmann',
    'author_email' => 'markus.hofmann@goldene-zeiten.de',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'products_core' => '1.0.0-1.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
