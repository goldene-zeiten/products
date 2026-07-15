<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Authentication;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * The inputs one OAuth 2.0 client-credentials token exchange needs, independent of any particular API.
 * A carrier or gateway builds this from its own resolved configuration and hands it to
 * {@see OAuth2ClientCredentialsProvider}; the token endpoint URL already carries the environment, so the
 * provider stays free of any per-vendor knowledge.
 */
#[Exclude]
final readonly class OAuth2Credentials
{
    public function __construct(
        public string $tokenUrl,
        public string $clientId,
        public string $clientSecret,
    ) {}

    /**
     * Cache key for the token these credentials obtain. It is scoped to the endpoint and the client id,
     * so a change of environment or client never reuses a stale token; the consuming package's own cache
     * keeps it separate from every other integration.
     */
    public function cacheIdentifier(): string
    {
        return 'token_' . md5($this->tokenUrl . '|' . $this->clientId);
    }
}
