<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products API Client',
    'description' => 'Shared OAuth2 and HTTP plumbing for the Products shop system\'s third-party API integrations',
    'category' => 'services',
    'author' => 'Markus Hofmann',
    'author_email' => 'typo3@calien.de',
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
