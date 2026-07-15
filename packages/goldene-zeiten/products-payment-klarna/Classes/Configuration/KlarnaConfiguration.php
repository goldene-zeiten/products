<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Configuration;

/**
 * Immutable, request-independent snapshot of the resolved Klarna configuration for one site.
 *
 * Built by {@see KlarnaConfigurationFactory} by layering the extension configuration under a site's
 * settings. Klarna authenticates with HTTP Basic credentials (an API-key username and its password).
 */
final readonly class KlarnaConfiguration
{
    public function __construct(
        public KlarnaEnvironment $environment,
        public string $username,
        public string $password,
        public string $apiBaseUrl = '',
    ) {}

    /**
     * Base URL for the Klarna API. Normally derived from the environment; an explicit override lets the
     * calls go through a proxy or a local mock without changing anything else.
     */
    public function baseUrl(): string
    {
        return $this->apiBaseUrl !== '' ? $this->apiBaseUrl : $this->environment->baseUrl();
    }

    /**
     * The HTTP Basic authorization header value for every Klarna call.
     */
    public function authorizationHeader(): string
    {
        return 'Basic ' . base64_encode($this->username . ':' . $this->password);
    }

    /**
     * Klarna is offered only when it can actually open a session, so missing credentials keep the method
     * hidden at checkout instead of failing when the customer picks it.
     */
    public function isComplete(): bool
    {
        return $this->username !== '' && $this->password !== '';
    }
}
