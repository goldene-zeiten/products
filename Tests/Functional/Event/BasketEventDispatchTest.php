<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Event;

use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Basket\BasketStorage;
use GoldeneZeiten\Products\Core\Tests\Functional\AbstractFrontendTestCase;
use GoldeneZeiten\Products\EventFixture\BasketUpdatedListener;
use GoldeneZeiten\Products\EventFixture\ModifyBasketItemListener;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class BasketEventDispatchTest extends AbstractFrontendTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-core',
        'goldene-zeiten/frontend-test',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketEventDispatchTest/basket.csv');
        BasketUpdatedListener::$invocationCount = 0;
        ModifyBasketItemListener::$invocationCount = 0;
    }

    #[Test]
    public function basketUpdatedEventIsDispatchedAndMutationPersists(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1);

        $this->assertGreaterThanOrEqual(1, BasketUpdatedListener::$invocationCount);
        $basket = $this->get(BasketStorage::class)->load($request);
        $items = $basket->getItems();
        $this->assertCount(2, $items);
        $this->assertSame(9999, $items[1]->getProductUid());
    }

    #[Test]
    public function modifyBasketItemEventIsDispatchedAndMutationTakesEffect(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $this->assertGreaterThanOrEqual(1, ModifyBasketItemListener::$invocationCount);
        $this->assertSame(4242, $item->getUnitPriceGross()->getCents());
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
