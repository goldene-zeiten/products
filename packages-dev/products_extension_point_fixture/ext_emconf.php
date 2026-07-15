<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Extension Point Fixture',
    'description' => 'Dummy implementations of the smaller tagged extension points (order-detail panel, legacy cleanup guard) proving their tagged_iterator wiring functionally, without shipping them in EXT:products.',
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
