<?php

declare(strict_types=1);

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Discount Fixture',
    'description' => 'Dummy DiscountProviderInterface implementations proving the tagged_iterator wiring functionally, without shipping real discount providers in EXT:products itself.',
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
