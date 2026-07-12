<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\EndToEnd;

use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Ensures stock is calculated correctly and doesn't produce a race condition on finalization.
 */
final class ConcurrentStockCheckoutFrontendTest extends AbstractFrontendTestCase
{
    /**
     * Raw (pre-hash) frontend session identifiers. The fixture's fe_sessions rows are keyed by
     * hash_hmac('sha256', $rawId, sha1($encryptionKey . 'core-session-backend')) of these exact
     * values (see the fixture's own comments) - DatabaseSessionBackend's own hashing scheme, using
     * this instance's fixed test encryptionKey ('i-am-not-a-secure-encryption-key', set by
     * TYPO3\TestingFramework\Core\Functional\FunctionalTestCase itself for every functional test).
     */
    private const SESSION_ID_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const SESSION_ID_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ConcurrentStockCheckoutFrontendTest/fixture.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ConcurrentStockCheckoutFrontendTest/fixture_sessions_' . $this->coreVersionSuffix() . '.csv');
    }

    #[Test]
    public function secondCustomersOrderIsRejectedWhenTheFirstAlreadyTookTheLastUnitInStock(): void
    {
        $preparedRequest = (new InternalRequest('http://localhost/checkout'))
            ->withQueryParameters([
                'tx_products_checkout[action]' => 'finalize',
                'tx_products_checkout[controller]' => 'Checkout',
                'cHash' => 'ec6f9d330cea28e55834e92f106c133c',
            ])
            ->withMethod('POST')
            ->withParsedBody([
                'tx_products_checkout[__referrer][@extension]' => 'Products',
                'tx_products_checkout[__referrer][@controller]' => 'Checkout',
                'tx_products_checkout[__referrer][@action]' => 'review',
                'tx_products_checkout[__referrer][arguments]' => 'YToxOntzOjY6ImFjdGlvbiI7czo2OiJyZXZpZXciO30=c00eb9b6b946e8aaef9780e313d95770cb6a0ca4',
                'tx_products_checkout[__referrer][@request]' => '{"@extension":"Products","@controller":"Checkout","@action":"review"}615d0c1e1285276c8c78ed26c9ee830afc280033',
                'tx_products_checkout[__trustedProperties]' => '[]6637f4b9001fbe272508b94d1b14afc40059dbee',
            ]);
        $firstRequest = $preparedRequest->withCookieParams(['fe_typo_user' => $this->sessionCookie(self::SESSION_ID_A)]);
        $secondRequest = $preparedRequest->withCookieParams(['fe_typo_user' => $this->sessionCookie(self::SESSION_ID_B)]);

        $firstResponse = $this->executeFrontendSubRequest($firstRequest);
        $secondResponse = $this->executeFrontendSubRequest($secondRequest);

        // Extbase's redirect() always returns a >=300 response, but core only propagates that
        // status out of a frontend-plugin cObject into the PSR-7 response the test harness
        // captures from TYPO3 v14 onwards (via the "frontend.response.data" request attribute).
        // TYPO3 v13 instead relays it through a bare header() call, which is invisible to
        // executeFrontendSubRequest()'s in-process dispatch, so v13 always reports 200 here
        // regardless of what the controller actually returned.
        // @todo Remove this switch once TYPO3 13 support is dropped and always assert 303.
        $expectedStatusCode = $this->coreVersionSuffix() === 'v13' ? 200 : 303;
        $this->assertSame($expectedStatusCode, $firstResponse->getStatusCode());
        $this->assertSame($expectedStatusCode, $secondResponse->getStatusCode());

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ConcurrentStockCheckoutFrontendTest/Result/after_both_finalize_calls.csv');
        $this->assertCSVDataSet(__DIR__ . '/Fixtures/ConcurrentStockCheckoutFrontendTest/Result/after_both_finalize_calls_sessions_' . $this->coreVersionSuffix() . '.csv');
    }

    /**
     * The JWT cookie value TYPO3's own frontend session middleware expects: it embeds only the raw
     * identifier (+ a "time" field that is never validated on decode, per JwtTrait/Firebase JWT -
     * no exp/nbf claim is set) signed with the same fixed test encryptionKey, so it is fully
     * reproducible at any time from just the raw identifier - never needs to be scraped from a
     * real login response.
     */
    private function sessionCookie(string $rawSessionId): string
    {
        return UserSession::createFromRecord($rawSessionId, [])->getJwt();
    }

    /**
     * TYPO3 14 also switched DatabaseSessionBackend::hash() from HMAC-SHA256 to HMAC-SHA3-256,
     * so the fixed SESSION_ID_A/B constants hash to different ses_id values per core version -
     * hence separate fixture/result CSVs per version, selected through this suffix.
     *
     * @todo Remove this switch once TYPO3 13 support is dropped and always use 'v14'.
     */
    private function coreVersionSuffix(): string
    {
        return (new Typo3Version())->getMajorVersion() < 14 ? 'v13' : 'v14';
    }
}
