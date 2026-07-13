<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\EndToEnd;

use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\StreamFactoryInterface;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Ensures stock is calculated correctly and doesn't produce a race condition on finalization.
 */
final class ConcurrentStockCheckoutFrontendTest extends AbstractFrontendTestCase
{
    /**
     * Raw frontend session identifiers hashed in the fixture per {@see DatabaseSessionBackend}.
     */
    private const SESSION_ID_A = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const SESSION_ID_B = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ConcurrentStockCheckoutFrontendTest/fixture.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/ConcurrentStockCheckoutFrontendTest/fixture_sessions_' . $this->coreVersionSuffix() . '.csv');
    }

    /**
     * The HMAC signing under __referrer/__trustedProperties/cHash covers the site's
     * encryptionKey plus the algorithm typo3/testing-framework and TYPO3 core use to
     * derive it, both of which changed between core 13 and 14 (same reason
     * {@see coreVersionSuffix()} exists for ses_id) - scraped once per version from a
     * real render of the review page's finalize form, same convention as this file's
     * other hardcoded tokens.
     *
     * @return array{cHash: string, referrerArguments: string, referrerRequest: string, trustedProperties: string}
     */
    private function finalizeFormTokens(): array
    {
        return $this->coreVersionSuffix() === 'v13'
            ? [
                'cHash' => 'ec6f9d330cea28e55834e92f106c133c',
                'referrerArguments' => 'YToxOntzOjY6ImFjdGlvbiI7czo2OiJyZXZpZXciO30=c00eb9b6b946e8aaef9780e313d95770cb6a0ca4',
                'referrerRequest' => '{"@extension":"Products","@controller":"Checkout","@action":"review"}615d0c1e1285276c8c78ed26c9ee830afc280033',
                'trustedProperties' => '{"termsAccepted":1}c40101db1a5f068ba6637fbf13e0a35a4fb5c8c5',
            ]
            : [
                'cHash' => '635c3c02e5ff1a38f8a215d87528941ea9446353a71ddc2eb6e6332b3dffd03c',
                'referrerArguments' => 'YToyOntzOjY6ImFjdGlvbiI7czo2OiJyZXZpZXciO3M6MTA6ImNvbnRyb2xsZXIiO3M6ODoiQ2hlY2tvdXQiO30=97627a9eaaf8e6b920ca85a4ff1236e49b514b64306090036d772458caae4478',
                'referrerRequest' => '{"@extension":"Products","@controller":"Checkout","@action":"review"}9933483dca4a9755ffffca110908282eb22cc0b60b0b42eed265c0b29c330813',
                'trustedProperties' => '{"termsAccepted":1}a6f4fec891056adb7746c8ee60bebea2ed2d731ff9df2fe070f81cd93298dc4f',
            ];
    }

    #[Test]
    public function secondCustomersOrderIsRejectedWhenTheFirstAlreadyTookTheLastUnitInStock(): void
    {
        $tokens = $this->finalizeFormTokens();
        $parsedBody = [
            'tx_products_checkout' => [
                '__referrer' => [
                    '@extension' => 'Products',
                    '@controller' => 'Checkout',
                    '@action' => 'review',
                    'arguments' => $tokens['referrerArguments'],
                    '@request' => $tokens['referrerRequest'],
                ],
                'termsAccepted' => '1',
                '__trustedProperties' => $tokens['trustedProperties'],
            ],
        ];
        // FunctionalTestCase only rebuilds the request body from parsedBody via
        // GuzzleHttp\Psr7\Query::build() when the body is still empty - and that
        // builder can't serialize doubly-nested arrays like __referrer above (it
        // triggers a PHP "Array to string conversion" warning, which this repo's
        // runTests.sh treats as a suite failure). Pre-filling the body with a
        // properly nested-array-aware encoder skips that reconstruction entirely;
        // getParsedBody() (what Extbase actually reads for argument mapping)
        // still returns $parsedBody untouched either way.
        $body = $this->get(StreamFactoryInterface::class)->createStream(http_build_query($parsedBody));
        $preparedRequest = (new InternalRequest('http://localhost/checkout'))
            ->withQueryParameters([
                'tx_products_checkout[action]' => 'finalize',
                'tx_products_checkout[controller]' => 'Checkout',
                'cHash' => $tokens['cHash'],
            ])
            ->withMethod('POST')
            ->withParsedBody($parsedBody)
            ->withBody($body);
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
     * Generates a reproducible JWT session cookie from the raw identifier.
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
