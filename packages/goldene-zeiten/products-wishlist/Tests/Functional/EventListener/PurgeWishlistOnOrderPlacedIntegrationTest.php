<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Wishlist\Tests\Functional\EventListener;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Wishlist\Service\WishlistService;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class PurgeWishlistOnOrderPlacedIntegrationTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-wishlist',
        'goldene-zeiten/frontend-test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PurgeWishlistOnOrderPlacedIntegrationTest/order_placement_wishlist_purge.csv');
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products-core', 'goldene-zeiten/frontend-test'],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
    }

    #[Test]
    public function placingAnOrderRemovesTheOrderedProductFromTheWishlistButKeepsTheRest(): void
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);

        $unitPriceNet = Money::fromDecimalString('84.03');
        $unitPriceGross = Money::fromDecimalString('100.00');
        $item = new BasketViewItem($product, null, 1, $unitPriceNet, $unitPriceGross, 0.19, $unitPriceNet, $unitPriceGross, $unitPriceGross->subtract($unitPriceNet));
        $basketViewModel = new BasketViewModel([$item], $unitPriceNet, $unitPriceGross, $unitPriceGross->subtract($unitPriceNet), 'EUR');

        $request = $this->requestForFrontendUser(5);
        $wishlistService = $this->get(WishlistService::class);
        $this->assertSame(['Product 1', 'Product 2'], $this->titlesOf($wishlistService->getItems($request)));

        $this->get(OrderCreationService::class)->create(
            $request,
            $basketViewModel,
            new CheckoutSelections([], ''),
            new Address(email: 'buyer@example.com', country: 'DE'),
            $this->get(PaymentMethodRegistry::class)->get('invoice')
        );

        $this->assertSame(['Product 2'], $this->titlesOf($wishlistService->getItems($request)));
    }

    /**
     * @param Product[] $products
     * @return string[]
     */
    private function titlesOf(array $products): array
    {
        return array_map(static fn(Product $product): string => $product->getTitle(), $products);
    }

    private function requestForFrontendUser(int $frontendUserUid): ServerRequest
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $frontendUser->user = ['uid' => $frontendUserUid];
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }
}
