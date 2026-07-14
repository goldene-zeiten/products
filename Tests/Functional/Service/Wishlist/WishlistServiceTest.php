<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Wishlist;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Service\Wishlist\WishlistService;
use GoldeneZeiten\Products\Core\Service\Wishlist\WishlistStorage;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class WishlistServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/wishlist.csv');
    }

    #[Test]
    #[DataProvider('frontendUserProvider')]
    public function aWishlistRoundTripsThroughItsBackingStorage(int $frontendUserUid): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor($frontendUserUid);

        $this->assertFalse($subject->contains($request, 1));
        $subject->add($request, 1);
        $this->assertTrue($subject->contains($request, 1));
        $this->assertSame(['Product 1'], $this->titlesOf($subject->getItems($request)));

        $subject->remove($request, 1);
        $this->assertFalse($subject->contains($request, 1));
        $this->assertSame([], $subject->getItems($request));
    }

    public static function frontendUserProvider(): \Generator
    {
        yield 'a guest' => ['frontendUserUid' => 0];
        yield 'an identified customer' => ['frontendUserUid' => 5];
    }

    #[Test]
    #[DataProvider('frontendUserProvider')]
    public function addingTheSameProductTwiceIsIdempotent(int $frontendUserUid): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor($frontendUserUid);

        $subject->add($request, 1);
        $subject->add($request, 1);

        $this->assertCount(1, $subject->getItems($request));
    }

    #[Test]
    public function removingAnAbsentProductIsANoOp(): void
    {
        $subject = $this->get(WishlistService::class);
        $guestRequest = $this->requestFor(0);
        $identifiedRequest = $this->requestFor(5);

        $subject->remove($guestRequest, 999);
        $subject->remove($identifiedRequest, 999);

        $this->assertSame([], $subject->getItems($guestRequest));
        $this->assertSame([], $subject->getItems($identifiedRequest));
    }

    #[Test]
    public function guestAndIdentifiedWishlistsAreNeverMerged(): void
    {
        $subject = $this->get(WishlistService::class);
        $guestRequest = $this->requestFor(0);
        $subject->add($guestRequest, 1);

        $identifiedRequest = $this->requestFor(5);
        $subject->add($identifiedRequest, 2);

        $this->assertSame(['Product 1'], $this->titlesOf($subject->getItems($guestRequest)));
        $this->assertSame(['Product 2'], $this->titlesOf($subject->getItems($identifiedRequest)));
    }

    #[Test]
    public function twoDifferentIdentifiedCustomersHaveIndependentWishlists(): void
    {
        $subject = $this->get(WishlistService::class);
        $customerA = $this->requestFor(5);
        $customerB = $this->requestFor(6);

        $subject->add($customerA, 1);

        $this->assertTrue($subject->contains($customerA, 1));
        $this->assertFalse($subject->contains($customerB, 1));
    }

    #[Test]
    #[DataProvider('frontendUserProvider')]
    public function moveUpAndMoveDownReorderTheWishlist(int $frontendUserUid): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor($frontendUserUid);
        $subject->add($request, 1);
        $subject->add($request, 2);
        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($subject->getItems($request)));

        $subject->moveUp($request, 2);
        $this->assertSame(['Product 2', 'Product 1'], $this->titlesOf($subject->getItems($request)));

        $subject->moveDown($request, 2);
        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($subject->getItems($request)));
    }

    #[Test]
    public function movingTheFirstItemUpIsANoOpForGuests(): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor(0);
        $subject->add($request, 1);
        $subject->add($request, 2);

        $subject->moveUp($request, 1);

        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($subject->getItems($request)));
    }

    #[Test]
    public function movingTheLastItemDownIsANoOpForIdentifiedCustomers(): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor(5);
        $subject->add($request, 1);
        $subject->add($request, 2);

        $subject->moveDown($request, 2);

        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($subject->getItems($request)));
    }

    #[Test]
    public function removeOrderedItemsPurgesOrderedProductsFromThePersistedWishlist(): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor(5);
        $subject->add($request, 1);
        $subject->add($request, 2);

        $subject->removeOrderedItems($this->orderFor(5, [1]));

        $this->assertSame(['Product 2'], $this->titlesOf($subject->getItems($request)));
    }

    #[Test]
    public function removeOrderedItemsIsANoOpForGuestOrders(): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor(5);
        $subject->add($request, 1);

        $subject->removeOrderedItems($this->orderFor(0, [1]));

        $this->assertSame(['Product 1'], $this->titlesOf($subject->getItems($request)));
    }

    #[Test]
    #[DataProvider('frontendUserProvider')]
    public function countReflectsTheWishlistSize(int $frontendUserUid): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor($frontendUserUid);
        $this->assertSame(0, $subject->count($request));

        $subject->add($request, 1);
        $subject->add($request, 2);

        $this->assertSame(2, $subject->count($request));
    }

    #[Test]
    public function mergeSessionIntoAccountMovesGuestWishlistItemsIntoThePersistedAccount(): void
    {
        $subject = $this->get(WishlistService::class);
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $guestRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $subject->add($guestRequest, 1);

        $frontendUser->user = ['uid' => 5];
        $identifiedRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $subject->mergeSessionIntoAccount($identifiedRequest);

        $this->assertSame(['Product 1'], $this->titlesOf($subject->getItems($identifiedRequest)));
        $this->assertSame([], $this->get(WishlistStorage::class)->load($identifiedRequest));
    }

    #[Test]
    public function mergeSessionIntoAccountDeduplicatesAgainstItemsAlreadyInTheAccount(): void
    {
        $subject = $this->get(WishlistService::class);
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $guestRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $subject->add($guestRequest, 1);

        $frontendUser->user = ['uid' => 5];
        $identifiedRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $subject->add($identifiedRequest, 1);

        $subject->mergeSessionIntoAccount($identifiedRequest);

        $this->assertCount(1, $subject->getItems($identifiedRequest));
    }

    #[Test]
    public function mergeSessionIntoAccountIsANoOpForAnonymousVisitors(): void
    {
        $subject = $this->get(WishlistService::class);
        $request = $this->requestFor(0);
        $subject->add($request, 1);

        $subject->mergeSessionIntoAccount($request);

        $this->assertSame(['Product 1'], $this->titlesOf($subject->getItems($request)));
    }

    #[Test]
    public function isEnabledReflectsTheSiteSetting(): void
    {
        $subject = $this->get(WishlistService::class);
        $requestWithoutSite = $this->requestFor(0);
        $this->assertFalse($subject->isEnabled($requestWithoutSite));

        $enabledSite = new Site('products', 1, ['settings' => ['products' => ['wishlist' => ['enabled' => true]]]]);
        $this->assertTrue($subject->isEnabled($requestWithoutSite->withAttribute('site', $enabledSite)));

        $disabledSite = new Site('products', 1, ['settings' => ['products' => ['wishlist' => ['enabled' => false]]]]);
        $this->assertFalse($subject->isEnabled($requestWithoutSite->withAttribute('site', $disabledSite)));
    }

    /**
     * @param Product[] $products
     * @return string[]
     */
    private function titlesOf(array $products): array
    {
        return array_map(static fn(Product $product): string => $product->getTitle(), $products);
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        if ($frontendUserUid > 0) {
            $frontendUser->user = ['uid' => $frontendUserUid];
        }
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    /**
     * @param int[] $productUids
     */
    private function orderFor(int $frontendUserUid, array $productUids): Order
    {
        $order = new Order();
        $order->setFrontendUser($frontendUserUid);
        foreach ($productUids as $productUid) {
            $item = new OrderItem();
            $item->setProduct($productUid);
            $order->getItems()->attach($item);
        }
        return $order;
    }
}
