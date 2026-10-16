<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\BasketSummary\Tests\Functional\Controller;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The two ways of placing the basket badge in a page template: the cache-safe one that fills itself in the
 * browser, and the uncached one rendered with the current count and total.
 */
final class BasketSummaryRenderingTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'cdcdcdcdcdcdcdcdcdcdcdcdcdcdcdcd';

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-basket-summary',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(self::sharedFixture('shop.csv'));
        $this->writeSiteConfiguration('products', $this->buildSiteConfiguration(1), [$this->buildDefaultLanguageConfiguration('en', '/')]);
        $this->setUpFrontendRootPage(1, [
            'constants' => [
                'EXT:products_core/Configuration/TypoScript/constants.typoscript',
            ],
            'setup' => [
                'EXT:products_core/Configuration/TypoScript/setup.typoscript',
                'EXT:products_basket_summary/Configuration/TypoScript/setup.typoscript',
            ],
        ]);
        $this->get(SessionManager::class)->getSessionBackend('FE')->set(self::SESSION_ID, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => 0,
            'ses_data' => serialize(['tx_products_basket' => '{"items":[{"productUid":5,"articleUid":null,"quantity":2},{"productUid":3,"articleUid":null,"quantity":1}]}']),
        ]);
    }

    #[Test]
    public function theCacheSafeBadgeAsksTheEndpoint(): void
    {
        $body = $this->renderPage('lib.productsBasketSummary');

        $this->assertStringContainsString('<products-basket-summary endpoint="/products/basket-summary"', $body);
        $this->assertStringContainsString('data-basket-count="" hidden="hidden">0</span>', $body);
        $this->assertStringContainsString('basket-summary.js', $body);
    }

    #[Test]
    public function theUncachedBadgeShowsTheCurrentCountAndTotal(): void
    {
        $body = $this->renderPage('lib.productsBasketSummaryUncached');

        $this->assertStringContainsString('data-basket-count="">3</span>', $body);
        $this->assertStringContainsString('€160.00', $body);
    }

    private function renderPage(string $lib): string
    {
        $this->addTypoScriptToTemplateRecord(1, '
            page = PAGE
            page.10 < ' . $lib . '
        ');
        $response = $this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/'))
                ->withCookieParams(['fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt()])
        );
        $this->assertSame(200, $response->getStatusCode());
        return (string)$response->getBody();
    }
}
