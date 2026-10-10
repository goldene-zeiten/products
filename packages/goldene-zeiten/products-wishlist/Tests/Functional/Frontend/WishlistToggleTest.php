<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Wishlist\Tests\Functional\Frontend;

use GoldeneZeiten\Products\Testing\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\CMS\Core\Session\SessionManager;
use TYPO3\CMS\Core\Session\UserSession;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The product list is cached, while a visitor's wishlist is their own: the toggle on a listed product must
 * reflect the wishlist of whoever views the page, never the one of the visitor the page was cached for.
 */
final class WishlistToggleTest extends AbstractFrontendTestCase
{
    private const SESSION_ID = 'wwwwwwwwwwwwwwwwwwwwwwwwwwwwwwww';

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-wishlist',
    ];

    /**
     * The page cache is what this is about, so it is switched back on for these tests.
     */
    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/catalog_with_wishlist.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(1, additionalRootConfiguration: [
                'dependencies' => $this->siteSetDependencies(),
                'settings' => [
                    'products' => [
                        'wishlist' => [
                            'enabled' => true,
                        ],
                        'pids' => [
                            'storageFolder' => 2,
                            'wishlistPage' => 3,
                        ],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $this->get(SessionManager::class)->getSessionBackend('FE')->set(self::SESSION_ID, [
            'ses_iplock' => '[DISABLED]',
            'ses_userid' => 0,
            'ses_data' => serialize(['tx_products_wishlist' => '[1]']),
        ]);
    }

    protected function siteSetDependencies(): array
    {
        return [
            'goldene-zeiten/products-core',
            'goldene-zeiten/products-wishlist',
            'goldene-zeiten/frontend-test',
        ];
    }

    #[Test]
    public function theListShowsTheWishlistStateOfTheVisitorViewingIt(): void
    {
        $owner = (string)$this->executeFrontendSubRequest($this->listRequest()->withCookieParams([
            'fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt(),
        ]))->getBody();
        $stranger = (string)$this->executeFrontendSubRequest($this->listRequest())->getBody();

        $this->assertSame(1, substr_count($owner, 'Remove from wishlist'), 'The owner sees product 1 on their wishlist.');
        $this->assertSame(1, substr_count($owner, 'Add to wishlist'));
        $this->assertSame(0, substr_count($stranger, 'Remove from wishlist'), 'Another visitor must not see the owner\'s wishlist.');
        $this->assertSame(2, substr_count($stranger, 'Add to wishlist'));
    }

    #[Test]
    public function theListServedFromThePageCacheStillShowsTheViewersOwnState(): void
    {
        $this->executeFrontendSubRequest($this->listRequest());

        $owner = (string)$this->executeFrontendSubRequest($this->listRequest()->withCookieParams([
            'fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt(),
        ]))->getBody();

        $this->assertSame(1, substr_count($owner, 'Remove from wishlist'));
    }

    #[Test]
    public function theWishlistShowsThePriceTheBasketCharges(): void
    {
        $body = (string)$this->executeFrontendSubRequest(
            (new InternalRequest('http://localhost/wishlist'))->withCookieParams([
                'fe_typo_user' => UserSession::createFromRecord(self::SESSION_ID, [])->getJwt(),
            ])
        )->getBody();

        $this->assertStringContainsString('Product 1', $body);
        $this->assertStringContainsString('90.00', $body);
        $this->assertStringNotContainsString('100.00', $body);
    }

    private function listRequest(): InternalRequest
    {
        return new InternalRequest('http://localhost/shop');
    }
}
