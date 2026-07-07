<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Wishlist;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\WishlistItemRepository;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Wishlist\WishlistService;
use GoldeneZeiten\Products\Service\Wishlist\WishlistStorage;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class WishlistServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private WishlistService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/wishlist.csv');
        $this->subject = new WishlistService(
            $this->get(WishlistItemRepository::class),
            $this->get(WishlistStorage::class),
            $this->get(ProductRepository::class),
            $this->get(FrontendUserResolver::class),
            $this->get(PersistenceManagerInterface::class),
            $this->fakeConfigurationManager()
        );
    }

    #[Test]
    public function aGuestsWishlistRoundTripsThroughTheSession(): void
    {
        $request = $this->requestFor(0);

        self::assertFalse($this->subject->contains($request, 1));
        $this->subject->add($request, 1);
        self::assertTrue($this->subject->contains($request, 1));
        self::assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($request)));

        $this->subject->remove($request, 1);
        self::assertFalse($this->subject->contains($request, 1));
        self::assertSame([], $this->subject->getItems($request));
    }

    #[Test]
    public function anIdentifiedCustomersWishlistRoundTripsThroughPersistedStorage(): void
    {
        $request = $this->requestFor(5);

        self::assertFalse($this->subject->contains($request, 1));
        $this->subject->add($request, 1);
        self::assertTrue($this->subject->contains($request, 1));
        self::assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($request)));

        $this->subject->remove($request, 1);
        self::assertFalse($this->subject->contains($request, 1));
        self::assertSame([], $this->subject->getItems($request));
    }

    #[Test]
    public function addingTheSameProductTwiceIsIdempotentForGuests(): void
    {
        $request = $this->requestFor(0);

        $this->subject->add($request, 1);
        $this->subject->add($request, 1);

        self::assertCount(1, $this->subject->getItems($request));
    }

    #[Test]
    public function addingTheSameProductTwiceIsIdempotentForIdentifiedCustomers(): void
    {
        $request = $this->requestFor(5);

        $this->subject->add($request, 1);
        $this->subject->add($request, 1);

        self::assertCount(1, $this->subject->getItems($request));
    }

    #[Test]
    public function removingAnAbsentProductIsANoOp(): void
    {
        $guestRequest = $this->requestFor(0);
        $identifiedRequest = $this->requestFor(5);

        $this->subject->remove($guestRequest, 999);
        $this->subject->remove($identifiedRequest, 999);

        self::assertSame([], $this->subject->getItems($guestRequest));
        self::assertSame([], $this->subject->getItems($identifiedRequest));
    }

    #[Test]
    public function guestAndIdentifiedWishlistsAreNeverMerged(): void
    {
        $guestRequest = $this->requestFor(0);
        $this->subject->add($guestRequest, 1);

        $identifiedRequest = $this->requestFor(5);
        $this->subject->add($identifiedRequest, 2);

        self::assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($guestRequest)));
        self::assertSame(['Product 2'], $this->titlesOf($this->subject->getItems($identifiedRequest)));
    }

    #[Test]
    public function twoDifferentIdentifiedCustomersHaveIndependentWishlists(): void
    {
        $customerA = $this->requestFor(5);
        $customerB = $this->requestFor(6);

        $this->subject->add($customerA, 1);

        self::assertTrue($this->subject->contains($customerA, 1));
        self::assertFalse($this->subject->contains($customerB, 1));
    }

    #[Test]
    public function isEnabledReflectsTheSiteSetting(): void
    {
        self::assertTrue($this->subject->isEnabled());

        $disabled = new WishlistService(
            $this->get(WishlistItemRepository::class),
            $this->get(WishlistStorage::class),
            $this->get(ProductRepository::class),
            $this->get(FrontendUserResolver::class),
            $this->get(PersistenceManagerInterface::class),
            $this->fakeConfigurationManager(enabled: false)
        );
        self::assertFalse($disabled->isEnabled());
    }

    /**
     * @param Product[] $products
     * @return string[]
     */
    private function titlesOf(array $products): array
    {
        return array_map(static fn(Product $product): string => $product->getTitle(), $products);
    }

    private function fakeConfigurationManager(bool $enabled = true): ConfigurationManagerInterface
    {
        return new class ($enabled) implements ConfigurationManagerInterface {
            public function __construct(
                private readonly bool $enabled
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['wishlist' => ['enabled' => $this->enabled]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        if ($frontendUserUid > 0) {
            $frontendUser->user = ['uid' => $frontendUserUid];
        }
        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }
}
