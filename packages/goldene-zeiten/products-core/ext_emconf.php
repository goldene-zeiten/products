<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products Core',
    'description' => 'A modern shop system for TYPO3',
    'category' => 'plugin',
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
        'suggests' => [
            'seo' => '13.4.0-14.3.99',
        ],
    ],
    'autoload' => [
        'psr-4' => [
            'GoldeneZeiten\\Products\\Core\\' => 'Classes/',
            'GoldeneZeiten\\Products\\Core\\Core13\\' => 'Core13/',
            'GoldeneZeiten\\Products\\Core\\Core14\\' => 'Core14/',
            'Dompdf\\' => [
                'Resources/Private/Php/ComposerVendor/dompdf/dompdf/src/',
                'Resources/Private/Php/ComposerVendor/dompdf/dompdf/lib/',
            ],
            'FontLib\\' => 'Resources/Private/Php/ComposerVendor/dompdf/php-font-lib/src/FontLib/',
            'Svg\\' => 'Resources/Private/Php/ComposerVendor/dompdf/php-svg-lib/src/Svg/',
            'Sabberworm\\CSS\\' => 'Resources/Private/Php/ComposerVendor/sabberworm/php-css-parser/src/',
        ],
    ],
];
