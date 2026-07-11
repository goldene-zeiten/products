<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\Basket\BasketStorage;
use GoldeneZeiten\Products\Tests\Functional\AbstractFrontendTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Regression coverage for a real (fixed) bug: Product::$basketMinQuantity/$basketMaxQuantity were
 * stored and even migrated from tt_products, but BasketService/Basket never read them - a merchant
 * could configure an order-quantity limit in the backend and it silently did nothing on the
 * storefront. Also covers the accompanying negative-quantity floor (a crafted quantity=-N request
 * could reduce/zero an existing basket line's total instead of only ever adding to it).
 */
final class BasketQuantityEnforcementTest extends AbstractFrontendTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/basket_service_quantity.csv');
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    #[Test]
    public function quantityBelowProductMinimumIsRaisedOnAdd(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1);

        $this->assertSame(2, $this->get(BasketStorage::class)->load($request)->getItems()[0]->getQuantity());
    }

    #[Test]
    public function quantityAboveProductMaximumIsLoweredOnAdd(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 99);

        $this->assertSame(5, $this->get(BasketStorage::class)->load($request)->getItems()[0]->getQuantity());
    }

    #[Test]
    public function nonZeroArticleBoundsOverrideTheProductsOnAdd(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 2, 1, 99);

        $this->assertSame(2, $this->get(BasketStorage::class)->load($request)->getItems()[0]->getQuantity());
    }

    #[Test]
    public function negativeQuantityCannotReduceAnExistingLineOnAdd(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 3);

        $basketService->add($request, 1, null, -10);

        $this->assertGreaterThanOrEqual(3, $this->get(BasketStorage::class)->load($request)->getItems()[0]->getQuantity());
    }

    #[Test]
    public function updateStillRemovesTheLineOnZeroOrLessRegardlessOfMinimum(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 3);

        $basketService->update($request, 1, null, 0);

        $this->assertSame([], $this->get(BasketStorage::class)->load($request)->getItems());
    }

    #[Test]
    public function updateAboveProductMaximumIsLowered(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 3);

        $basketService->update($request, 1, null, 99);

        $this->assertSame(5, $this->get(BasketStorage::class)->load($request)->getItems()[0]->getQuantity());
    }

    private function anonymousSessionRequest(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
    }
}
