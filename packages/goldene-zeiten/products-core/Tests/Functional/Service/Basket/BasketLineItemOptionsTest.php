<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Basket;

use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Basket\Exception\LineItemOptionsRejectedException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalExclusionService;
use GoldeneZeiten\Products\LineOptionsFixture\LineItemOptionsListener;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Options a customer chooses for a basket line - an engraving, a ring size - come only from a listener
 * resolving the submitted selections; they make the line distinct and add their surcharge to its price.
 */
final class BasketLineItemOptionsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-line-options-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/BasketLineItemOptionsTest/products.csv');
    }

    protected function tearDown(): void
    {
        LineItemOptionsListener::$enabled = false;
        LineItemOptionsListener::$engravingRequired = false;
        parent::tearDown();
    }

    #[Test]
    public function selectionsNoListenerResolvesAreDropped(): void
    {
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $items = $basketService->getBasketViewModel($request)->getItems();

        $this->assertCount(1, $items);
        $this->assertSame([], $items[0]->getLineOptions());
        $this->assertSame('', $items[0]->getOptionsKey());
        $this->assertSame(10000, $items[0]->getUnitPriceGross()->getCents());
    }

    #[Test]
    public function resolvedOptionsArePartOfTheLineAndItsPrice(): void
    {
        LineItemOptionsListener::$enabled = true;
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 2, ['engraving' => ' Anna ', 'giftBox' => '1', 'unknown' => 'x']);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $options = $item->getLineOptions();
        $this->assertCount(2, $options);
        $this->assertSame('Engraving', $options[0]->getLabel());
        $this->assertSame('Anna', $options[0]->getValueLabel());
        $this->assertSame(500, $options[0]->getSurcharge()->getCents());
        $this->assertSame('Yes', $options[1]->getValueLabel());
        $this->assertSame(10500, $item->getUnitPriceGross()->getCents());
        $this->assertSame(21000, $item->getLineTotalGross()->getCents());
    }

    #[Test]
    public function aSurchargeInANetPricedShopIsTaxedLikeThePrice(): void
    {
        LineItemOptionsListener::$enabled = true;
        $basketService = $this->get(BasketService::class);
        $site = new Site('products', 1, ['settings' => ['products' => ['pricing' => ['mode' => 'net']]]]);
        $request = $this->anonymousSessionRequest()->withAttribute('site', $site);

        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $item = $basketService->getBasketViewModel($request)->getItems()[0];

        $this->assertSame(12495, $item->getUnitPriceGross()->getCents());
        $this->assertSame(595, $item->getLineOptions()[0]->getSurcharge()->getCents());
    }

    #[Test]
    public function differentSelectionsAreSeparateLines(): void
    {
        LineItemOptionsListener::$enabled = true;
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $basketService->add($request, 1, null, 1, ['engraving' => 'Ben']);
        $basketService->add($request, 1, null, 1);
        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $items = $basketService->getBasketViewModel($request)->getItems();

        $this->assertCount(3, $items);
        $this->assertSame(2, $items[0]->getQuantity());
        $this->assertSame(1, $items[1]->getQuantity());
        $this->assertSame([], $items[2]->getLineOptions());
    }

    #[Test]
    public function rejectedSelectionsAreNotAdded(): void
    {
        LineItemOptionsListener::$enabled = true;
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        try {
            $basketService->add($request, 1, null, 1, ['engraving' => 'Far too long for a ring']);
            $this->fail('A rejected selection must not be added.');
        } catch (LineItemOptionsRejectedException $exception) {
            $this->assertSame(1792173011, $exception->getCode());
        }

        $this->assertSame([], $basketService->getBasketViewModel($request)->getItems());
    }

    #[Test]
    public function aLineWithoutSelectionsCanBeRefusedToo(): void
    {
        LineItemOptionsListener::$enabled = true;
        LineItemOptionsListener::$engravingRequired = true;
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();

        $this->expectException(LineItemOptionsRejectedException::class);
        $this->expectExceptionCode(1792173011);

        $basketService->add($request, 1, null, 1);
    }

    #[Test]
    public function aStoredLineWithoutSelectionsLeavesTheBasketWhenOptionsBecomeRequired(): void
    {
        LineItemOptionsListener::$enabled = true;
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 1);

        LineItemOptionsListener::$engravingRequired = true;

        $this->assertSame([], $basketService->getBasketViewModel($request)->getItems());
    }

    #[Test]
    public function updateAndRemoveAddressTheLineOfTheGivenOptions(): void
    {
        LineItemOptionsListener::$enabled = true;
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $basketService->add($request, 1, null, 1, ['engraving' => 'Ben']);
        [$anna, $ben] = $basketService->getBasketViewModel($request)->getItems();

        $basketService->update($request, 1, null, 3, $ben->getOptionsKey());
        $basketService->remove($request, 1, null, $anna->getOptionsKey());
        $items = $basketService->getBasketViewModel($request)->getItems();

        $this->assertCount(1, $items);
        $this->assertSame('Ben', $items[0]->getLineOptions()[0]->getValueLabel());
        $this->assertSame(3, $items[0]->getQuantity());
    }

    #[Test]
    public function aLineWhoseOptionsNoLongerResolveLeavesTheBasket(): void
    {
        LineItemOptionsListener::$enabled = true;
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $basketService->add($request, 1, null, 1);

        LineItemOptionsListener::$enabled = false;
        $items = $basketService->getBasketViewModel($request)->getItems();

        $this->assertCount(1, $items);
        $this->assertSame([], $items[0]->getLineOptions());
    }

    #[Test]
    public function aPersonalizingOptionExcludesTheLineFromWithdrawal(): void
    {
        LineItemOptionsListener::$enabled = true;
        $basketService = $this->get(BasketService::class);
        $request = $this->anonymousSessionRequest();
        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $basketService->add($request, 1, null, 1, ['giftBox' => '1']);
        $basket = $basketService->getBasketViewModel($request);

        $this->assertSame(WithdrawalExclusion::PERSONALIZED, $basket->getItems()[0]->getWithdrawalExclusion());
        $this->assertSame(WithdrawalExclusion::NONE, $basket->getItems()[1]->getWithdrawalExclusion());
        $this->assertTrue($this->get(WithdrawalExclusionService::class)->basketContainsPersonalized($basket));
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
