<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Legacy Schema Fixture',
    'description' => 'Trimmed legacy tt_products schema for functional tests of the upgrade wizards.',
    'category' => 'misc',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
        ],
    ],
];
