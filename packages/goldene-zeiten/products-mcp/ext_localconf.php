<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Session\Backend\DatabaseSessionBackend;

defined('TYPO3') or die();

// The sessions of the MCP clients. Point the identifier at another backend to keep them elsewhere.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['session']['products_mcp'] ??= [
    'backend' => DatabaseSessionBackend::class,
    'options' => [
        'table' => 'tx_productsmcp_session',
    ],
];

// The baskets assistants build. They are kept apart from the MCP sessions because the garbage collection of a
// session backend deletes by age across its whole table, and a basket has to outlive the session.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['session']['products_mcp_basket'] ??= [
    'backend' => DatabaseSessionBackend::class,
    'options' => [
        'table' => 'tx_productsmcp_basket',
    ],
];
