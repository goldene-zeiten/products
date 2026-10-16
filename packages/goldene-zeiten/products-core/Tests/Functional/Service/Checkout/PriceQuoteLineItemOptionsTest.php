<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Checkout;

use GoldeneZeiten\Products\Core\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Core\Service\Order\Exception\PriceQuoteExpiredException;
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
 * The reviewed price is bound to the options of each line: other options are another basket.
 */
final class PriceQuoteLineItemOptionsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-line-options-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PriceQuoteServiceTest/price_quote.csv');
        LineItemOptionsListener::$enabled = true;
    }

    protected function tearDown(): void
    {
        LineItemOptionsListener::$enabled = false;
        parent::tearDown();
    }

    #[Test]
    public function theResolvedQuoteKeepsTheOptionsOfItsLines(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $priceQuoteService = $this->get(PriceQuoteService::class);
        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $priceQuoteService->freeze($request, $basketService->getBasketViewModel($request));

        $resolved = $priceQuoteService->resolve(
            $request,
            $basketService->getBasketViewModel($request),
            $this->get(ProductsConfigurationFactory::class)->create($request)
        );

        $this->assertSame('Anna', $resolved->getItems()[0]->getLineOptions()[0]->getValueLabel());
        $this->assertSame(2500, $resolved->getItems()[0]->getUnitPriceGross()->getCents());
    }

    #[Test]
    public function otherOptionsThanTheReviewedOnesNeedANewReview(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $priceQuoteService = $this->get(PriceQuoteService::class);
        $basketService->add($request, 1, null, 1, ['engraving' => 'Anna']);
        $priceQuoteService->freeze($request, $basketService->getBasketViewModel($request));
        $anna = $basketService->getBasketViewModel($request)->getItems()[0];
        $basketService->remove($request, 1, null, $anna->getOptionsKey());
        $basketService->add($request, 1, null, 1, ['engraving' => 'Ben']);

        $this->expectException(PriceQuoteExpiredException::class);
        $this->expectExceptionCode(1751950000);

        $priceQuoteService->resolve(
            $request,
            $basketService->getBasketViewModel($request),
            $this->get(ProductsConfigurationFactory::class)->create($request)
        );
    }

    private function requestFor(): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $frontendUser->user = ['uid' => 0];
        $site = new Site('products', 1, ['settings' => ['products' => []]]);
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $site);
    }
}
