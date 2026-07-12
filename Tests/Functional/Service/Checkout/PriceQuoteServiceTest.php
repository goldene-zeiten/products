<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Checkout;

use GoldeneZeiten\Products\Configuration\ProductsConfiguration;
use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Service\Order\Exception\PriceQuoteExpiredException;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Persistence\Generic\PersistenceManager;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Verifies the review-page price freeze survives a live price change up to order
 * placement (EU Consumer Rights Directive Art. 8(2) / §312j BGB: the price shown directly
 * before the customer places a binding order must be the price charged) - and that a
 * changed basket, or a stale quote, forces a fresh review instead of silently charging a
 * different amount.
 */
final class PriceQuoteServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/PriceQuoteServiceTest/price_quote.csv');
    }

    #[Test]
    public function resolveHonorsTheFrozenPriceEvenAfterTheLivePriceChanges(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $priceQuoteService = $this->get(PriceQuoteService::class);
        $basketService->add($request, 1, null, 1);

        $reviewedBasket = $basketService->getBasketViewModel($request);
        $this->assertSame(2000, $reviewedBasket->getItems()[0]->getUnitPriceGross()->getCents());
        $priceQuoteService->freeze($request, $reviewedBasket);

        // Simulate a price change (e.g. a public price period going live) after review.
        $this->addActivePricePeriod(1, '10.00');
        $this->get(PersistenceManager::class)->clearState(); // force a fresh fetch, bypassing Extbase's identity map

        $liveBasket = $basketService->getBasketViewModel($request);
        $this->assertSame(1000, $liveBasket->getItems()[0]->getUnitPriceGross()->getCents(), 'Sanity check: the live price really did change.');

        $resolved = $priceQuoteService->resolve($request, $liveBasket, $this->configuration($request));

        $this->assertSame(2000, $resolved->getItems()[0]->getUnitPriceGross()->getCents(), 'The reviewed price must still apply, not the changed live price.');
    }

    #[Test]
    public function resolveReturnsTheLiveBasketUnchangedWhenNoQuoteWasEverFrozen(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, 1, null, 1);
        $liveBasket = $basketService->getBasketViewModel($request);

        $resolved = $this->get(PriceQuoteService::class)->resolve($request, $liveBasket, $this->configuration($request));

        $this->assertSame($liveBasket->getTotalGross()->getCents(), $resolved->getTotalGross()->getCents());
    }

    #[Test]
    public function resolveRejectsAChangedBasket(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $priceQuoteService = $this->get(PriceQuoteService::class);
        $basketService->add($request, 1, null, 1);
        $priceQuoteService->freeze($request, $basketService->getBasketViewModel($request));

        $basketService->update($request, 1, null, 2);
        $liveBasket = $basketService->getBasketViewModel($request);

        $this->expectException(PriceQuoteExpiredException::class);
        $priceQuoteService->resolve($request, $liveBasket, $this->configuration($request));
    }

    #[Test]
    public function resolveRejectsAnExpiredQuote(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $priceQuoteService = $this->get(PriceQuoteService::class);
        $basketService->add($request, 1, null, 1);
        $priceQuoteService->freeze($request, $basketService->getBasketViewModel($request));
        $this->ageStoredQuote($request, 1000); // older than the default 900s validity window

        $liveBasket = $basketService->getBasketViewModel($request);

        $this->expectException(PriceQuoteExpiredException::class);
        $priceQuoteService->resolve($request, $liveBasket, $this->configuration($request));
    }

    #[Test]
    public function resolveAcceptsAQuoteWellWithinTheValidityWindow(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $priceQuoteService = $this->get(PriceQuoteService::class);
        $basketService->add($request, 1, null, 1);
        $priceQuoteService->freeze($request, $basketService->getBasketViewModel($request));

        $liveBasket = $basketService->getBasketViewModel($request);
        $resolved = $priceQuoteService->resolve($request, $liveBasket, $this->configuration($request));

        $this->assertSame(2000, $resolved->getItems()[0]->getUnitPriceGross()->getCents());
    }

    private function configuration(ServerRequestInterface $request): ProductsConfiguration
    {
        return $this->get(ProductsConfigurationFactory::class)->create($request);
    }

    private function addActivePricePeriod(int $productUid, string $price): void
    {
        $connection = $this->get(ConnectionPool::class)->getConnectionForTable('tx_products_domain_model_priceperiod');
        $now = time();
        $connection->insert('tx_products_domain_model_priceperiod', [
            'pid' => 2,
            'tstamp' => $now,
            'crdate' => $now,
            'product' => $productUid,
            'fe_group' => 0,
            'price' => $price,
            'valid_from' => $now - 3600,
            'valid_until' => $now + 3600,
        ]);
    }

    /**
     * Rewrites the already-stored quote's timestamp to simulate elapsed time, without
     * actually sleeping in the test.
     */
    private function ageStoredQuote(ServerRequestInterface $request, int $secondsOld): void
    {
        $frontendUser = $request->getAttribute('frontend.user');
        $this->assertInstanceOf(FrontendUserAuthentication::class, $frontendUser);
        $quote = json_decode((string)$frontendUser->getKey('ses', 'tx_products_checkout_price_quote'), true);
        $this->assertIsArray($quote);
        $quote['timestamp'] = time() - $secondsOld;
        $frontendUser->setKey('ses', 'tx_products_checkout_price_quote', json_encode($quote));
        $frontendUser->storeSessionData();
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
