<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Shipping Fallback Fixture',
    'description' => 'A single conditional ShippingProviderInterface implementation for testing that the built-in fallback carrier yields to a real carrier and fills back in when it cannot serve a basket.',
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
