<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Line Options Fixture',
    'description' => 'Resolves basket line options to prove EXT:products prices, stores and snapshots them.',
    'category' => 'misc',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'products' => '1.0.0-1.99.99',
        ],
    ],
];
