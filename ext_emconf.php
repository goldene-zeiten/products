<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'ProductsCore',
    'description' => 'A modern shop system for TYPO3',
    'category' => 'plugin',
    'author' => 'Markus Hofmann',
    'author_email' => 'markus.hofmann@goldene-zeiten.de',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
