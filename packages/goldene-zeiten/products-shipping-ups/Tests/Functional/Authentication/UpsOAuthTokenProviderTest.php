<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Authentication;

use GoldeneZeiten\Products\Shipping\Ups\Authentication\UpsOAuthTokenProvider;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsConfiguration;
use GoldeneZeiten\Products\Shipping\Ups\Configuration\UpsEnvironment;
use GoldeneZeiten\Products\Shipping\Ups\Exception\UpsAuthenticationException;
use GoldeneZeiten\Products\Shipping\Ups\Tests\Functional\Fake\FakeHttpClient;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\CacheManager;

final class UpsOAuthTokenProviderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-shipping-ups',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->get(CacheManager::class)->getCache('products_shipping_ups_token')->flush();
    }

    #[Test]
    public function fetchesAndThenReusesTheCachedToken(): void
    {
        $client = new FakeHttpClient();
        $client->willReturn(FakeHttpClient::jsonResponse(200, ['access_token' => 'TOKEN-1', 'expires_in' => '3599']));
        $subject = $this->subject($client);

        $this->assertSame('TOKEN-1', $subject->getToken($this->configuration()));
        $this->assertSame('TOKEN-1', $subject->getToken($this->configuration()));
        $this->assertCount(1, $client->received, 'The token endpoint is called only once.');
    }

    #[Test]
    public function sendsTheClientCredentialsGrantWithBasicAuth(): void
    {
        $client = new FakeHttpClient();
        $client->willReturn(FakeHttpClient::jsonResponse(200, ['access_token' => 'T', 'expires_in' => '3599']));

        $this->subject($client)->getToken($this->configuration());

        $request = $client->lastRequest();
        $this->assertSame('https://wwwcie.ups.com/security/v1/oauth/token', (string)$request->getUri());
        $this->assertSame('Basic ' . base64_encode('cid:secret'), $request->getHeaderLine('Authorization'));
        $this->assertSame('grant_type=client_credentials', (string)$request->getBody());
    }

    #[Test]
    public function forceRefreshBypassesTheCache(): void
    {
        $client = new FakeHttpClient();
        $client->willReturn(FakeHttpClient::jsonResponse(200, ['access_token' => 'T1', 'expires_in' => '3599']));
        $client->willReturn(FakeHttpClient::jsonResponse(200, ['access_token' => 'T2', 'expires_in' => '3599']));
        $subject = $this->subject($client);

        $this->assertSame('T1', $subject->getToken($this->configuration()));
        $this->assertSame('T2', $subject->getToken($this->configuration(), true));
    }

    #[Test]
    public function anErrorStatusRaisesAnAuthenticationException(): void
    {
        $client = new FakeHttpClient();
        $client->willReturn(FakeHttpClient::jsonResponse(401, ['response' => ['errors' => [['code' => '401', 'message' => 'x']]]]));

        $this->expectException(UpsAuthenticationException::class);
        $this->subject($client)->getToken($this->configuration());
    }

    private function subject(FakeHttpClient $client): UpsOAuthTokenProvider
    {
        return new UpsOAuthTokenProvider(
            $client,
            $this->get(CacheManager::class)->getCache('products_shipping_ups_token'),
        );
    }

    private function configuration(): UpsConfiguration
    {
        return new UpsConfiguration(UpsEnvironment::Sandbox, 'cid', 'secret', 'ACC', '80331', 'DE', '', 'KGS', []);
    }
}
