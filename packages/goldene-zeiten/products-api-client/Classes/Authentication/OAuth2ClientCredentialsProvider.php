<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Authentication;

use GoldeneZeiten\Products\ApiClient\Exception\OAuth2AuthenticationException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Obtains and caches OAuth 2.0 bearer tokens via the client-credentials grant - the flow UPS, PayPal and
 * most REST payment/carrier APIs share: HTTP Basic credentials plus a `grant_type=client_credentials`
 * form body.
 *
 * Tokens are reused across requests until shortly before they expire. The cache is injected by the
 * consuming package, so each integration keeps its own token store; the provider itself holds no
 * per-vendor state. A caller that hits a 401 (a token the gateway revoked early) asks again with
 * $forceRefresh to bypass the cache once.
 */
final class OAuth2ClientCredentialsProvider
{
    /**
     * Renew a little before the real expiry so an in-flight request never races the cut-off.
     */
    private const REFRESH_SAFETY_FACTOR = 0.8;

    public function __construct(
        private readonly ApiHttpClient $httpClient,
        private readonly FrontendInterface $tokenCache,
    ) {}

    public function getToken(OAuth2Credentials $credentials, bool $forceRefresh = false): string
    {
        $cacheIdentifier = $credentials->cacheIdentifier();
        if (!$forceRefresh) {
            $cached = $this->tokenCache->get($cacheIdentifier);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
        }

        return $this->requestAndCacheToken($credentials, $cacheIdentifier);
    }

    private function requestAndCacheToken(OAuth2Credentials $credentials, string $cacheIdentifier): string
    {
        $data = $this->requestToken($credentials);
        $token = (string)($data['access_token'] ?? '');
        if ($token === '') {
            throw new OAuth2AuthenticationException('The OAuth token endpoint returned no access token.', 1752600100);
        }

        $lifetime = max(60, (int)((int)($data['expires_in'] ?? 3600) * self::REFRESH_SAFETY_FACTOR));
        $this->tokenCache->set($cacheIdentifier, $token, [], $lifetime);

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    private function requestToken(OAuth2Credentials $credentials): array
    {
        $response = $this->send($credentials);
        if ($response->getStatusCode() !== 200) {
            throw new OAuth2AuthenticationException(
                sprintf('The OAuth token endpoint returned HTTP %d.', $response->getStatusCode()),
                1752600101,
            );
        }

        $data = json_decode((string)$response->getBody(), true);
        if (!is_array($data)) {
            throw new OAuth2AuthenticationException('The OAuth token response was not valid JSON.', 1752600102);
        }

        return $data;
    }

    private function send(OAuth2Credentials $credentials): ResponseInterface
    {
        return $this->httpClient->postForm(
            $credentials->tokenUrl,
            'grant_type=client_credentials',
            [
                'Authorization' => 'Basic ' . base64_encode($credentials->clientId . ':' . $credentials->clientSecret),
            ],
        );
    }
}
