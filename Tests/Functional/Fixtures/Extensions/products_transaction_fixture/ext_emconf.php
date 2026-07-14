<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Placement Transaction Fixture',
    'description' => 'Aborts an order placement from inside the placement transaction, so tests can prove the whole placement rolls back instead of leaving a half-written order behind.',
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
