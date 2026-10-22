<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Mcp\Sdk;

use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Session\SessionManager;

/**
 * Creates the store for MCP sessions on the session backend configured as "products_mcp".
 */
final readonly class SessionStoreFactory
{
    public const SESSION_IDENTIFIER = 'products_mcp';

    public function __construct(
        private SessionManager $sessionManager,
        private Context $context
    ) {}

    public function create(int $lifetime): Typo3SessionStore
    {
        return new Typo3SessionStore(
            $this->sessionManager->getSessionBackend(self::SESSION_IDENTIFIER),
            $lifetime,
            $this->context
        );
    }
}
