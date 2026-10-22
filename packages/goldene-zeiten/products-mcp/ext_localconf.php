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
