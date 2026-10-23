<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products MCP',
    'description' => 'Model Context Protocol endpoint that lets AI assistants search and explain the Products shop',
    'category' => 'services',
    'author' => 'Markus Hofmann',
    'author_email' => 'typo3@calien.de',
    'state' => 'alpha',
    'clearCacheOnLoad' => 0,
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.3.99',
            'products_core' => '1.0.0-1.99.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
    'autoload' => [
        'psr-4' => [
            'GoldeneZeiten\\Products\\Mcp\\' => 'Classes/',
            'Mcp\\' => 'Resources/Private/Php/ComposerVendor/mcp/sdk/src/',
            'Opis\\JsonSchema\\' => 'Resources/Private/Php/ComposerVendor/opis/json-schema/src/',
            'Opis\\String\\' => 'Resources/Private/Php/ComposerVendor/opis/string/src/',
            'Opis\\Uri\\' => 'Resources/Private/Php/ComposerVendor/opis/uri/src/',
            'Http\\Discovery\\' => 'Resources/Private/Php/ComposerVendor/php-http/discovery/src/',
        ],
    ],
];
