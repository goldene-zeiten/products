<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\ApiClient\Tests\Functional\Authentication;

use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2ClientCredentialsProvider;
use GoldeneZeiten\Products\ApiClient\Authentication\OAuth2Credentials;
use GoldeneZeiten\Products\ApiClient\Exception\OAuth2AuthenticationException;
use GoldeneZeiten\Products\ApiClient\Http\ApiHttpClient;
use GoldeneZeiten\Products\Testing\AbstractApiMockTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\TransientMemoryBackend;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

/**
 * Exercises the shared client-credentials token provider over the real HTTP path against WireMock, using
 * a generic OAuth stub (the same flow every consuming integration relies on).
 */
final class OAuth2ClientCredentialsProviderTest extends AbstractApiMockTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-api-client',
    ];

    /**
     * A throwaway token cache for the test run. Built by the CacheManager so its backend is constructed
     * the right way for the running core major, rather than instantiated by hand.
     *
     * @var array<string, mixed>
     */
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'products_api_client_test_token' => [
                        'frontend' => VariableFrontend::class,
                        'backend' => TransientMemoryBackend::class,
                    ],
                ],
            ],
        ],
    ];

    private const CACHE_IDENTIFIER = 'products_api_client_test_token';

    private const TOKEN_PATH = '/api-client/oauth/token';

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenCache()->flush();
    }

    #[Test]
    public function fetchesTheTokenOverHttpAndReusesTheCachedValue(): void
    {
        $subject = $this->subject();

        $this->assertSame('MOCK-ACCESS-TOKEN', $subject->getToken($this->credentials()));
        $this->assertSame('MOCK-ACCESS-TOKEN', $subject->getToken($this->credentials()));
        $this->assertSame(1, $this->recordedRequests(self::TOKEN_PATH), 'The token endpoint is called only once.');
    }

    #[Test]
    public function forceRefreshFetchesANewToken(): void
    {
        $subject = $this->subject();
        $subject->getToken($this->credentials());
        $subject->getToken($this->credentials(), true);

        $this->assertSame(2, $this->recordedRequests(self::TOKEN_PATH));
    }

    #[Test]
    public function sendsTheClientCredentialsGrantWithBasicAuth(): void
    {
        $this->subject()->getToken($this->credentials());

        $request = $this->loggedRequests(self::TOKEN_PATH)[0];
        $this->assertStringStartsWith('Basic ', $request['headers']['Authorization']);
        $this->assertSame('grant_type=client_credentials', $request['body']);
    }

    #[Test]
    public function anAuthFailureRaisesAnAuthenticationException(): void
    {
        $this->expectException(OAuth2AuthenticationException::class);
        $this->expectExceptionCode(1752600101);
        $this->subject()->getToken($this->credentials('authfail'));
    }

    private function subject(): OAuth2ClientCredentialsProvider
    {
        return new OAuth2ClientCredentialsProvider(new ApiHttpClient($this->httpClient()), $this->tokenCache());
    }

    private function tokenCache(): FrontendInterface
    {
        return $this->get(CacheManager::class)->getCache(self::CACHE_IDENTIFIER);
    }

    private function credentials(string $clientId = 'mock-client'): OAuth2Credentials
    {
        return new OAuth2Credentials($this->mockRoot . self::TOKEN_PATH, $clientId, 'secret');
    }
}
