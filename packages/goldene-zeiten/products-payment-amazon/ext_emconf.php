<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Amazon Pay Payment',
    'description' => 'Amazon Pay (Checkout v2) payment method for the Products shop system',
    'category' => 'services',
    'author' => 'Markus Hofmann',
    'author_email' => 'typo3@calien.de',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'products_api_client' => '1.0.0-1.99.99',
            'products_core' => '1.0.0-1.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'GoldeneZeiten\\Products\\Payment\\Amazon\\' => 'Classes/',
            'Amazon\\Pay\\API\\' => 'Resources/Private/Php/ComposerVendor/amzn/amazon-pay-api-sdk-php/Amazon/Pay/API/',
            'phpseclib3\\' => 'Resources/Private/Php/ComposerVendor/phpseclib/phpseclib/phpseclib/',
            'ParagonIE\\ConstantTime\\' => 'Resources/Private/Php/ComposerVendor/paragonie/constant_time_encoding/src/',
        ],
    ],
];
