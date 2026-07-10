<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Wishlist;

use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Wishlist\WishlistStorage;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class WishlistStorageTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function addingSucceedsWhenCookieConsentIsNotRequired(): void
    {
        $storage = $this->subject(requireCookieConsent: false);
        $request = $this->request();

        $storage->add($request, 1);

        self::assertSame([1], $storage->load($request));
    }

    #[Test]
    public function addingIsSkippedWhenCookieConsentIsRequiredButNotYetConfirmed(): void
    {
        $storage = $this->subject(requireCookieConsent: true);
        $request = $this->request();

        $storage->add($request, 1);

        self::assertSame([], $storage->load($request));
    }

    #[Test]
    public function addingSucceedsWhenCookieConsentIsRequiredAndAlreadyConfirmed(): void
    {
        $storage = $this->subject(requireCookieConsent: true);
        $request = $this->requestWithConfirmedCookie();

        $storage->add($request, 1);

        self::assertSame([1], $storage->load($request));
    }

    private function subject(bool $requireCookieConsent): WishlistStorage
    {
        return new WishlistStorage(
            $this->get(FrontendUserResolver::class),
            $this->fakeConfigurationManager($requireCookieConsent)
        );
    }

    private function fakeConfigurationManager(bool $requireCookieConsent): ConfigurationManagerInterface
    {
        return new class ($requireCookieConsent) implements ConfigurationManagerInterface {
            public function __construct(
                private readonly bool $requireCookieConsent
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['session' => ['requireCookieConsent' => $this->requireCookieConsent]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }

    private function request(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }

    private function requestWithConfirmedCookie(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withCookieParams([$frontendUser->name => 'existing-session-id']);
    }
}
