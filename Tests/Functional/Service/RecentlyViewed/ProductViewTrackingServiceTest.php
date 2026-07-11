<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\RecentlyViewed;

use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Service\RecentlyViewed\ProductViewTrackingService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class ProductViewTrackingServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private ProductViewTrackingService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/product_view_tracking.csv');
        $this->subject = $this->get(ProductViewTrackingService::class);
    }

    #[Test]
    public function recordIncrementsTheSiteWideCounterAcrossAnonymousVisitors(): void
    {
        $this->subject->record($this->requestFor(0), 1);
        $this->subject->record($this->requestFor(0), 1);
        $this->subject->record($this->requestFor(0), 2);

        self::assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getMostViewed(10)));
    }

    #[Test]
    public function mostViewedOrdersByDescendingViewCount(): void
    {
        $this->subject->record($this->requestFor(0), 2);
        $this->subject->record($this->requestFor(0), 1);
        $this->subject->record($this->requestFor(0), 1);

        self::assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getMostViewed(10)));
    }

    #[Test]
    public function anonymousVisitorsNeverGetAPerUserRecord(): void
    {
        $this->subject->record($this->requestFor(0), 1);

        self::assertSame([], $this->subject->getMostViewedByUser($this->requestFor(0), 10));
    }

    #[Test]
    public function identifiedShoppersGetTheirOwnMostViewedListing(): void
    {
        $this->subject->record($this->requestFor(5), 1);
        $this->subject->record($this->requestFor(5), 1);
        $this->subject->record($this->requestFor(5), 2);

        self::assertSame(['Product 1', 'Product 2'], $this->titlesOf($this->subject->getMostViewedByUser($this->requestFor(5), 10)));
    }

    #[Test]
    public function perUserListingsAreIndependentBetweenShoppers(): void
    {
        $this->subject->record($this->requestFor(5), 1);
        $this->subject->record($this->requestFor(6), 2);

        self::assertSame(['Product 1'], $this->titlesOf($this->subject->getMostViewedByUser($this->requestFor(5), 10)));
        self::assertSame(['Product 2'], $this->titlesOf($this->subject->getMostViewedByUser($this->requestFor(6), 10)));
    }

    #[Test]
    public function getMostViewedRespectsTheLimit(): void
    {
        $this->subject->record($this->requestFor(0), 1);
        $this->subject->record($this->requestFor(0), 2);

        self::assertCount(1, $this->subject->getMostViewed(1));
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
        return (new ServerRequest('http://localhost/'))->withAttribute('frontend.user', $frontendUser);
    }
}
