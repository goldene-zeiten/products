<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Controller;

use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Service\Order\OrderTokenService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderControllerTest/guest_order.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderControllerTest/order_history_content.csv');
    }

    #[Test]
    public function listActionShowsOwnOrdersForLoggedInFrontendUser(): void
    {
        $request = (new InternalRequest('http://localhost/shop'))
            ->withCookieParams(['fe_typo_user' => $this->loginFrontendUser(1)]);
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('ORD-1', (string)$response->getBody());
    }

    #[Test]
    public function listActionIsEmptyForAnonymousVisitor(): void
    {
        $request = new InternalRequest('http://localhost/shop');
        $response = $this->executeFrontendSubRequest($request);

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringNotContainsString('ORD-1', (string)$response->getBody());
        $this->assertStringContainsString('No orders found.', (string)$response->getBody());
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

        $this->assertStringNotContainsString('ORD-1', (string)$response->getBody());
    }

    #[Test]
    #[DataProvider('guestOrderAccessProvider')]
    public function showActionHandlesGuestOrderAccessByHash(bool $useValidHash): void
    {
        $hash = null;
        if ($useValidHash) {
            $order = $this->get(OrderRepository::class)->findByUidIgnoringStoragePage(2);
            $this->assertNotNull($order);
            $hash = $this->get(OrderTokenService::class)->generateToken($order);
        }

        $request = $this->orderShowRequest(2, $hash);
        $response = $this->executeFrontendSubRequest($request);

        if ($useValidHash) {
            $this->assertSame(200, $response->getStatusCode());
            $this->assertStringContainsString('ORD-2', (string)$response->getBody());
        } else {
            $this->assertStringNotContainsString('ORD-2', (string)$response->getBody());
        }
    }

    public static function guestOrderAccessProvider(): \Generator
    {
        yield 'anonymous visitor denied without hash' => [
            'useValidHash' => false,
        ];

        yield 'anonymous visitor allowed with valid hash' => [
            'useValidHash' => true,
        ];
    }

    private function orderShowRequest(int $order, ?string $hash): InternalRequest
    {
        $queryParameters = [
            'tx_products_orderhistory[action]' => 'show',
            'tx_products_orderhistory[order]' => $order,
        ];
        if ($hash !== null) {
            $queryParameters['tx_products_orderhistory[hash]'] = $hash;
        }

        $parameterString = '&id=2';
        foreach ($queryParameters as $key => $value) {
            $parameterString .= '&' . $key . '=' . $value;
        }
        $cHash = $this->get(CacheHashCalculator::class)->generateForParameters($parameterString);

        return (new InternalRequest('http://localhost/shop'))
            ->withQueryParameters(array_merge($queryParameters, ['cHash' => $cHash]));
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
