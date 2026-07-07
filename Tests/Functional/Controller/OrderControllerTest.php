<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\CMS\Frontend\Page\CacheHashCalculator;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

final class OrderControllerTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/orders_with_frontend_user.csv');
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/order_history_content.csv');
    }

    #[Test]
    public function listActionShowsOwnOrdersForLoggedInFrontendUser(): void
    {
        $request = (new InternalRequest('http://localhost/shop'))
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringContainsString('ORD-1', (string)$response->getBody());
    }

    #[Test]
    public function listActionIsEmptyForAnonymousVisitor(): void
    {
        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        self::assertSame(200, $response->getStatusCode());
        self::assertStringNotContainsString('ORD-1', (string)$response->getBody());
        self::assertStringContainsString('No orders found.', (string)$response->getBody());
    }

    #[Test]
    public function showActionRedirectsWhenOrderBelongsToAnotherFrontendUser(): void
    {
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters(
            '&id=2&tx_products_orderhistory[action]=show&tx_products_orderhistory[order]=1'
        );
        $request = (new InternalRequest('http://localhost/shop'))
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(2)])
            ->withQueryParameters([
                'tx_products_orderhistory[action]' => 'show',
                'tx_products_orderhistory[order]' => 1,
                'cHash' => $cHash,
            ]);
        $response = $this->executeFrontendSubRequest($request);

        self::assertStringNotContainsString('ORD-1', (string)$response->getBody());
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
