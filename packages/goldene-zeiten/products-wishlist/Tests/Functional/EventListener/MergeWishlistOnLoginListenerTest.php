<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Wishlist\Tests\Functional\EventListener;

use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Wishlist\EventListener\MergeWishlistOnLoginListener;
use GoldeneZeiten\Products\Wishlist\Service\WishlistService;
use GoldeneZeiten\Products\Wishlist\Service\WishlistStorage;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Authentication\Event\AfterUserLoggedInEvent;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Regression: listener constructor runs before Extbase bootstrap, so dependencies must read
 * settings from site config, not ConfigurationManagerInterface.
 */
final class MergeWishlistOnLoginListenerTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-wishlist',
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
