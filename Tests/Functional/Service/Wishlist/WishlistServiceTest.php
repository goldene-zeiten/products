<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Wishlist;

use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Service\Wishlist\WishlistService;
use GoldeneZeiten\Products\Service\Wishlist\WishlistStorage;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
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
        'goldene-zeiten/products',
    ];

    private WishlistService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/wishlist.csv');
        $this->subject = $this->get(WishlistService::class);
    }

    #[Test]
    public function aGuestsWishlistRoundTripsThroughTheSession(): void
    {
        $request = $this->requestFor(0);

        $this->assertFalse($this->subject->contains($request, 1));
        $this->subject->add($request, 1);
        $this->assertTrue($this->subject->contains($request, 1));
        $this->assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($request)));

        $this->subject->remove($request, 1);
        $this->assertFalse($this->subject->contains($request, 1));
        $this->assertSame([], $this->subject->getItems($request));
    }

    #[Test]
    public function anIdentifiedCustomersWishlistRoundTripsThroughPersistedStorage(): void
    {
        $request = $this->requestFor(5);

        $this->assertFalse($this->subject->contains($request, 1));
        $this->subject->add($request, 1);
        $this->assertTrue($this->subject->contains($request, 1));
        $this->assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($request)));

        $this->subject->remove($request, 1);
        $this->assertFalse($this->subject->contains($request, 1));
        $this->assertSame([], $this->subject->getItems($request));
    }

    #[Test]
    public function addingTheSameProductTwiceIsIdempotentForGuests(): void
    {
        $request = $this->requestFor(0);

        $this->subject->add($request, 1);
        $this->subject->add($request, 1);

        $this->assertCount(1, $this->subject->getItems($request));
    }

    #[Test]
    public function addingTheSameProductTwiceIsIdempotentForIdentifiedCustomers(): void
    {
        $request = $this->requestFor(5);

        $this->subject->add($request, 1);
        $this->subject->add($request, 1);

        $this->assertCount(1, $this->subject->getItems($request));
    }

    #[Test]
    public function removingAnAbsentProductIsANoOp(): void
    {
        $guestRequest = $this->requestFor(0);
        $identifiedRequest = $this->requestFor(5);

        $this->subject->remove($guestRequest, 999);
        $this->subject->remove($identifiedRequest, 999);

        $this->assertSame([], $this->subject->getItems($guestRequest));
        $this->assertSame([], $this->subject->getItems($identifiedRequest));
    }

    #[Test]
    public function guestAndIdentifiedWishlistsAreNeverMerged(): void
    {
        $guestRequest = $this->requestFor(0);
        $this->subject->add($guestRequest, 1);

        $identifiedRequest = $this->requestFor(5);
        $this->subject->add($identifiedRequest, 2);

        $this->assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($guestRequest)));
        $this->assertSame(['Product 2'], $this->titlesOf($this->subject->getItems($identifiedRequest)));
    }

    #[Test]
    public function twoDifferentIdentifiedCustomersHaveIndependentWishlists(): void
    {
        $customerA = $this->requestFor(5);
        $customerB = $this->requestFor(6);

        $this->subject->add($customerA, 1);

        $this->assertTrue($this->subject->contains($customerA, 1));
        $this->assertFalse($this->subject->contains($customerB, 1));
    }

    #[Test]
    public function moveUpAndMoveDownReorderAGuestsSessionWishlist(): void
    {
        $request = $this->requestFor(0);
        $this->subject->add($request, 1);
        $this->subject->add($request, 2);
        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getItems($request)));

        $this->subject->moveUp($request, 2);
        $this->assertSame(['Product 2', 'Product 1'], $this->titlesOf($this->subject->getItems($request)));

        $this->subject->moveDown($request, 2);
        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getItems($request)));
    }

    #[Test]
    public function movingTheFirstItemUpIsANoOpForGuests(): void
    {
        $request = $this->requestFor(0);
        $this->subject->add($request, 1);
        $this->subject->add($request, 2);

        $this->subject->moveUp($request, 1);

        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getItems($request)));
    }

    #[Test]
    public function moveUpAndMoveDownReorderAnIdentifiedCustomersPersistedWishlist(): void
    {
        $request = $this->requestFor(5);
        $this->subject->add($request, 1);
        $this->subject->add($request, 2);
        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getItems($request)));

        $this->subject->moveUp($request, 2);
        $this->assertSame(['Product 2', 'Product 1'], $this->titlesOf($this->subject->getItems($request)));

        $this->subject->moveDown($request, 2);
        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getItems($request)));
    }

    #[Test]
    public function movingTheLastItemDownIsANoOpForIdentifiedCustomers(): void
    {
        $request = $this->requestFor(5);
        $this->subject->add($request, 1);
        $this->subject->add($request, 2);

        $this->subject->moveDown($request, 2);

        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getItems($request)));
    }

    #[Test]
    public function removeOrderedItemsPurgesOrderedProductsFromThePersistedWishlist(): void
    {
        $request = $this->requestFor(5);
        $this->subject->add($request, 1);
        $this->subject->add($request, 2);

        $this->subject->removeOrderedItems($this->orderFor(5, [1]));

        $this->assertSame(['Product 2'], $this->titlesOf($this->subject->getItems($request)));
    }

    #[Test]
    public function removeOrderedItemsIsANoOpForGuestOrders(): void
    {
        $request = $this->requestFor(5);
        $this->subject->add($request, 1);

        $this->subject->removeOrderedItems($this->orderFor(0, [1]));

        $this->assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($request)));
    }

    #[Test]
    public function countReflectsAGuestsSessionWishlistWithoutHydratingProducts(): void
    {
        $request = $this->requestFor(0);
        $this->assertSame(0, $this->subject->count($request));

        $this->subject->add($request, 1);
        $this->subject->add($request, 2);

        $this->assertSame(2, $this->subject->count($request));
    }

    #[Test]
    public function countReflectsAnIdentifiedCustomersPersistedWishlist(): void
    {
        $request = $this->requestFor(5);
        $this->assertSame(0, $this->subject->count($request));

        $this->subject->add($request, 1);
        $this->subject->add($request, 2);

        $this->assertSame(2, $this->subject->count($request));
    }

    #[Test]
    public function mergeSessionIntoAccountMovesGuestWishlistItemsIntoThePersistedAccount(): void
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $guestRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->subject->add($guestRequest, 1);

        $frontendUser->user = ['uid' => 5];
        $identifiedRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->subject->mergeSessionIntoAccount($identifiedRequest);

        $this->assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($identifiedRequest)));
        $this->assertSame([], $this->get(WishlistStorage::class)->load($identifiedRequest));
    }

    #[Test]
    public function mergeSessionIntoAccountDeduplicatesAgainstItemsAlreadyInTheAccount(): void
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $guestRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->subject->add($guestRequest, 1);

        $frontendUser->user = ['uid' => 5];
        $identifiedRequest = (new ServerRequest('http://localhost/'))
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->subject->add($identifiedRequest, 1);

        $this->subject->mergeSessionIntoAccount($identifiedRequest);

        $this->assertCount(1, $this->subject->getItems($identifiedRequest));
    }

    #[Test]
    public function mergeSessionIntoAccountIsANoOpForAnonymousVisitors(): void
    {
        $request = $this->requestFor(0);
        $this->subject->add($request, 1);

        $this->subject->mergeSessionIntoAccount($request);

        $this->assertSame(['Product 1'], $this->titlesOf($this->subject->getItems($request)));
    }

    #[Test]
    public function isEnabledReflectsTheSiteSetting(): void
    {
        $requestWithoutSite = $this->requestFor(0);
        $this->assertFalse($this->subject->isEnabled($requestWithoutSite));

        $enabledSite = new Site('products', 1, ['settings' => ['products' => ['wishlist' => ['enabled' => true]]]]);
        $this->assertTrue($this->subject->isEnabled($requestWithoutSite->withAttribute('site', $enabledSite)));

        $disabledSite = new Site('products', 1, ['settings' => ['products' => ['wishlist' => ['enabled' => false]]]]);
        $this->assertFalse($this->subject->isEnabled($requestWithoutSite->withAttribute('site', $disabledSite)));
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
