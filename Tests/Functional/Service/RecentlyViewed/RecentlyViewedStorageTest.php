<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\RecentlyViewed;

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
        self::assertSame([], $this->subject()->load($this->request()));
    }

    #[Test]
    public function recordingAProductAddsItToTheFront(): void
    {
        $storage = $this->subject();
        $request = $this->request();

        $storage->record($request, 1);
        $storage->record($request, 2);

        self::assertSame([2, 1], $storage->load($request));
    }

    #[Test]
    public function reviewingAnAlreadyPresentProductMovesItToTheFrontWithoutDuplicating(): void
    {
        $storage = $this->subject();
        $request = $this->request();

        $storage->record($request, 1);
        $storage->record($request, 2);
        $storage->record($request, 1);

        self::assertSame([1, 2], $storage->load($request));
    }

    #[Test]
    public function theListIsCappedAtTheConfiguredLimit(): void
    {
        $storage = $this->subject(limit: 2);
        $request = $this->request();

        $storage->record($request, 1);
        $storage->record($request, 2);
        $storage->record($request, 3);

        self::assertSame([3, 2], $storage->load($request));
    }

    #[Test]
    public function aGuestWithoutASessionRecordsNothingAndCrashesNothing(): void
    {
        $storage = $this->subject();
        $request = new ServerRequest('http://localhost/');

        $storage->record($request, 1);

        self::assertSame([], $storage->load($request));
    }

    private function subject(int $limit = 10): RecentlyViewedStorage
    {
        return new RecentlyViewedStorage($this->fakeConfigurationManager($limit));
    }

    private function fakeConfigurationManager(int $limit): ConfigurationManagerInterface
    {
        return new class ($limit) implements ConfigurationManagerInterface {
            public function __construct(
                private readonly int $limit
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['recentlyViewed' => ['limit' => $this->limit]];
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
}
