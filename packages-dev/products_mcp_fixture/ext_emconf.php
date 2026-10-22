<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Products MCP Fixture',
    'description' => 'Listeners that add, remove and rewrite MCP tools and resources to prove EXT:products_mcp dispatches its events.',
    'category' => 'misc',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-14.99.99',
            'products_mcp' => '1.0.0-1.99.99',
        ],
    ],
];
