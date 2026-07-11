<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\RecentlyViewed;

use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\RecentlyViewed\RecentlyViewedStorage;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class RecentlyViewedStorageTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function loadIsEmptyByDefault(): void
    {
        $this->assertSame([], $this->subject()->load($this->request()));
    }

    #[Test]
    public function recordingAProductAddsItToTheFront(): void
    {
        $storage = $this->subject();
        $request = $this->request();

        $storage->record($request, 1);
        $storage->record($request, 2);

        $this->assertSame([2, 1], $storage->load($request));
    }

    #[Test]
    public function reviewingAnAlreadyPresentProductMovesItToTheFrontWithoutDuplicating(): void
    {
        $storage = $this->subject();
        $request = $this->request();

        $storage->record($request, 1);
        $storage->record($request, 2);
        $storage->record($request, 1);

        $this->assertSame([1, 2], $storage->load($request));
    }

    #[Test]
    public function theListIsCappedAtTheConfiguredLimit(): void
    {
        $storage = $this->subject(limit: 2);
        $request = $this->request();

        $storage->record($request, 1);
        $storage->record($request, 2);
        $storage->record($request, 3);

        $this->assertSame([3, 2], $storage->load($request));
    }

    #[Test]
    public function aGuestWithoutASessionRecordsNothingAndCrashesNothing(): void
    {
        $storage = $this->subject();
        $request = new ServerRequest('http://localhost/');

        $storage->record($request, 1);

        $this->assertSame([], $storage->load($request));
    }

    #[Test]
    public function recordingIsSkippedWhenCookieConsentIsRequiredButNotYetConfirmed(): void
    {
        $storage = $this->subject(requireCookieConsent: true);
        $request = $this->request();

        $storage->record($request, 1);

        $this->assertSame([], $storage->load($request));
    }

    #[Test]
    public function recordingSucceedsWhenCookieConsentIsRequiredAndAlreadyConfirmed(): void
    {
        $storage = $this->subject(requireCookieConsent: true);
        $request = $this->requestWithConfirmedCookie();

        $storage->record($request, 1);

        $this->assertSame([1], $storage->load($request));
    }

    private function subject(int $limit = 10, bool $requireCookieConsent = false): RecentlyViewedStorage
    {
        return new RecentlyViewedStorage(
            $this->get(FrontendUserResolver::class),
            $this->fakeConfigurationManager($limit, $requireCookieConsent)
        );
    }

    private function fakeConfigurationManager(int $limit, bool $requireCookieConsent): ConfigurationManagerInterface
    {
        return new class ($limit, $requireCookieConsent) implements ConfigurationManagerInterface {
            public function __construct(
                private readonly int $limit,
                private readonly bool $requireCookieConsent
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return [
                    'recentlyViewed' => ['limit' => $this->limit],
                    'session' => ['requireCookieConsent' => $this->requireCookieConsent],
                ];
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
