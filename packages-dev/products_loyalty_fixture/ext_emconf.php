<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Loyalty Fixture',
    'description' => 'Dummy LoyaltyProviderInterface implementation proving the tagged_iterator wiring functionally, without shipping real loyalty providers in EXT:products itself.',
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
