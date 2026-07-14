<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Wishlist;

use GoldeneZeiten\Products\Core\Service\Wishlist\WishlistStorage;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class WishlistStorageTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    /**
     * @param int[] $expectedLoad
     */
    #[Test]
    #[DataProvider('addProvider')]
    public function addBehavesAccordingToCookieConsentState(bool $requireCookieConsent, bool $confirmedCookie, array $expectedLoad): void
    {
        $storage = $this->get(WishlistStorage::class);
        $request = $confirmedCookie
            ? $this->requestWithConfirmedCookie(requireCookieConsent: $requireCookieConsent)
            : $this->request(requireCookieConsent: $requireCookieConsent);

        $storage->add($request, 1);

        $this->assertSame($expectedLoad, $storage->load($request));
    }

    public static function addProvider(): \Generator
    {
        yield 'succeeds when cookie consent is not required' => ['requireCookieConsent' => false, 'confirmedCookie' => false, 'expectedLoad' => [1]];
        yield 'skipped when cookie consent is required but not yet confirmed' => ['requireCookieConsent' => true, 'confirmedCookie' => false, 'expectedLoad' => []];
        yield 'succeeds when cookie consent is required and already confirmed' => ['requireCookieConsent' => true, 'confirmedCookie' => true, 'expectedLoad' => [1]];
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
