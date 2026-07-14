<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Event Fixture',
    'description' => 'Records and mutates dispatched PSR-14 events to prove EXT:products dispatches them.',
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
