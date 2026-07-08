<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class CheckoutAddressPrefillTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/checkout_content.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/checkout_address_prefill.csv');
    }

    #[Test]
    public function addressActionPrefillsFromReturningCustomersLastOrder(): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_products_checkout[action]=address'
        );
        $request = (new InternalRequest('http://localhost/shop'))
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)])
            ->withQueryParameters([
                'tx_products_checkout[action]' => 'address',
                'cHash' => $cHash,
            ]);
        $response = $this->executeFrontendSubRequest($request);
        $body = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('Loop Street 42', $body);
        self::assertStringContainsString('Repeatville', $body);
        self::assertStringContainsString('returning@example.com', $body);
    }

    #[Test]
    public function addressActionIsBlankForAnonymousVisitor(): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_products_checkout[action]=address'
        );
        $request = (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters([
                'tx_products_checkout[action]' => 'address',
                'cHash' => $cHash,
            ]);
        $response = $this->executeFrontendSubRequest($request);
        $body = (string)$response->getBody();

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('Loop Street 42', $body);
    }

    /**
     * Simulates a logged-in frontend user for the next `executeFrontendSubRequest()` call
     * by persisting a matching `fe_sessions` row and building a valid `fe_typo_user` cookie
     * (a signed JWT wrapping the session identifier), without going through a real login flow.
     *
     * @return string the value to use for the `fe_typo_user` cookie
     */
    private function loginFrontendUser(int $frontendUserUid): string
    {
        $sessionId = bin2hex(random_bytes(16));
        $sessionBackend = $this->get(SessionManager::class)->getSessionBackend('FE');
        $sessionBackend->set($sessionId, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => $frontendUserUid,
        ]);

        return UserSession::createFromRecord($sessionId, [])->getJwt();
    }
}
