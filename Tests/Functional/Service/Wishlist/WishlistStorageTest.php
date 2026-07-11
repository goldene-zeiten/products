<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Wishlist;

use GoldeneZeiten\Products\Service\Wishlist\WishlistStorage;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class WishlistStorageTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function addingSucceedsWhenCookieConsentIsNotRequired(): void
    {
        $storage = $this->get(WishlistStorage::class);
        $request = $this->request(requireCookieConsent: false);

        $storage->add($request, 1);

        $this->assertSame([1], $storage->load($request));
    }

    #[Test]
    public function addingIsSkippedWhenCookieConsentIsRequiredButNotYetConfirmed(): void
    {
        $storage = $this->get(WishlistStorage::class);
        $request = $this->request(requireCookieConsent: true);

        $storage->add($request, 1);

        $this->assertSame([], $storage->load($request));
    }

    #[Test]
    public function addingSucceedsWhenCookieConsentIsRequiredAndAlreadyConfirmed(): void
    {
        $storage = $this->get(WishlistStorage::class);
        $request = $this->requestWithConfirmedCookie(requireCookieConsent: true);

        $storage->add($request, 1);

        $this->assertSame([1], $storage->load($request));
    }

    private function request(bool $requireCookieConsent): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $this->siteWithCookieConsentSetting($requireCookieConsent));
    }

    private function requestWithConfirmedCookie(bool $requireCookieConsent): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $this->siteWithCookieConsentSetting($requireCookieConsent))
            ->withCookieParams([$frontendUser->name => 'existing-session-id']);
    }

    private function siteWithCookieConsentSetting(bool $requireCookieConsent): Site
    {
        return new Site('products', 1, [
            'settings' => ['products' => ['session' => ['requireCookieConsent' => $requireCookieConsent]]],
        ]);
    }
}
