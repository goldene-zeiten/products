<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\EventListener;

use GoldeneZeiten\Products\EventListener\MergeWishlistOnLoginListener;
use GoldeneZeiten\Products\Service\Wishlist\WishlistService;
use GoldeneZeiten\Products\Service\Wishlist\WishlistStorage;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Regression coverage for a real bug found in Phase 13 verification: this listener is constructed
 * via DI from `TYPO3\CMS\Frontend\Middleware\FrontendUserAuthenticator`, which fires *before*
 * Extbase's own bootstrap ever runs for the request - unlike every other place
 * `WishlistService`/`WishlistStorage` are normally constructed (an Extbase controller action).
 * Both previously read settings eagerly via `ConfigurationManagerInterface` in their own
 * constructors, which requires an Extbase-bootstrapped request and crashed with
 * `NoServerRequestGivenException` the instant the listener (and therefore its dependencies) were
 * constructed this early - silently 500ing every frontend login. Fixed by reading
 * `products.wishlist.enabled`/`products.session.requireCookieConsent` off
 * `$request->getAttribute('site')->getSettings()` instead (already this project's convention
 * elsewhere, e.g. `OrderFactory`/`StorageFolderResolver`), which needs only `SiteResolver` to have
 * run - true well before `FrontendUserAuthenticator` in the middleware stack, and requires no
 * Extbase involvement at all.
 */
final class MergeWishlistOnLoginListenerTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function invokingTheListenerDuringTheLoginMiddlewareDoesNotThrow(): void
    {
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/wishlist.csv');

        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $frontendUser->user = ['uid' => 5];

        $guestRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $this->get(WishlistStorage::class)->add($guestRequest, 1);

        $middlewareStageRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_FE);
        $listener = $this->get(MergeWishlistOnLoginListener::class);

        $listener(new AfterUserLoggedInEvent($frontendUser, $middlewareStageRequest));

        $persisted = $this->get(WishlistService::class)->getItems($middlewareStageRequest);
        $this->assertCount(1, $persisted);
        $this->assertSame([], $this->get(WishlistStorage::class)->load($middlewareStageRequest));
    }
}
