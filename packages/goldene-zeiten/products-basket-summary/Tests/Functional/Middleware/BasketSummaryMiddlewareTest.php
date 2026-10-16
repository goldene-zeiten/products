<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\BasketSummary\Tests\Functional\Middleware;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The page header asks for the basket's item count and total without the cached page itself depending on
 * the basket.
 */
final class BasketSummaryMiddlewareTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'abababababababababababababababab';

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-basket-summary',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
    }

    #[Test]
    public function theSummaryReportsItemCountAndTotalOfTheBasket(): void
    {
        $this->startSession('{"items":[{"productUid":5,"articleUid":null,"quantity":2},{"productUid":3,"articleUid":null,"quantity":1}]}');

        $response = $this->executeFrontendSubRequest($this->summaryRequest());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertStringContainsString('application/json', $response->getHeaderLine('Content-Type'));
        $this->assertStringContainsString('no-store', $response->getHeaderLine('Cache-Control'));
        $summary = json_decode((string)$response->getBody(), true);
        $this->assertSame(3, $summary['count']);
        $this->assertSame('EUR', $summary['currency']);
        $this->assertSame('160.00', $summary['total']);
    }

    #[Test]
    public function anEmptyBasketReportsNothing(): void
    {
        $response = $this->executeFrontendSubRequest($this->summaryRequest());

        $summary = json_decode((string)$response->getBody(), true);
        $this->assertSame(0, $summary['count']);
        $this->assertSame('0.00', $summary['total']);
    }

    #[Test]
    public function otherPathsAreLeftToThePageRendering(): void
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest('http://localhost/shop'));

        $this->assertStringContainsString('text/html', $response->getHeaderLine('Content-Type'));
    }

    private function summaryRequest(): InternalRequest
    {
        return (new InternalRequest('http://localhost/products/basket-summary'))
            ->withCookieParams(['fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt()]);
    }

    private function startSession(string $basket): void
    {
        $this->get(SessionManager::class)->getSessionBackend('FE')->set(self::SESSION_ID, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => 0,
            'ses_data' => serialize(['tx_products_basket' => $basket]),
        ]);
    }
}
