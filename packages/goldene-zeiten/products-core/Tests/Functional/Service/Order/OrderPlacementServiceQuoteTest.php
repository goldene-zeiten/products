<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Checkout\PriceQuoteService;
use GoldeneZeiten\Products\Core\Service\Order\Exception\PriceQuoteExpiredException;
use GoldeneZeiten\Products\Core\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
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
 * End-to-end proof that a price reviewed on the checkout review step is what an actually
 * placed Order is charged, even if the live catalog/period price changes before the
 * customer clicks "place order" - and that a changed basket or an expired quote blocks
 * order placement entirely rather than silently charging a different price.
 */
final class OrderPlacementServiceQuoteTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderPlacementServiceQuoteTest/order_placement_quote.csv');
    }

    #[Test]
    public function placeHonorsTheReviewedPriceEvenAfterALivePriceChange(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, 1, null, 1);
        $this->get(PriceQuoteService::class)->freeze($request, $basketService->getBasketViewModel($request));

        $this->addActivePricePeriod(1, '5.00');
        $this->get(PersistenceManager::class)->clearState(); // force a fresh fetch, bypassing Extbase's identity map

        $order = $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice', new CheckoutChoices(termsAccepted: true))->getOrder();

        $this->assertSame(2000, $order->getTotalGross()->getCents(), 'The order must be charged the reviewed price (20.00), not the changed live price (5.00).');
    }

    #[Test]
    public function placeRejectsAnExpiredQuoteAndCreatesNoOrder(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, 1, null, 1);
        $priceQuoteService = $this->get(PriceQuoteService::class);
        $priceQuoteService->freeze($request, $basketService->getBasketViewModel($request));
        $this->ageStoredQuote($request, 1000);

        try {
            $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice');
            $this->fail('Expected PriceQuoteExpiredException was not thrown.');
        } catch (PriceQuoteExpiredException) {
            // expected
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    #[Test]
    public function placeRejectsAChangedBasketAndCreatesNoOrder(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, 1, null, 1);
        $this->get(PriceQuoteService::class)->freeze($request, $basketService->getBasketViewModel($request));

        $basketService->update($request, 1, null, 3);

        try {
            $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice');
            $this->fail('Expected PriceQuoteExpiredException was not thrown.');
        } catch (PriceQuoteExpiredException) {
            // expected
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    #[Test]
    public function placeWithoutEverReviewingFallsBackToTheLivePrice(): void
    {
        $request = $this->requestFor();
        $basketService = $this->get(BasketService::class);
        $basketService->add($request, 1, null, 1);

        $order = $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice', new CheckoutChoices(termsAccepted: true))->getOrder();

        $this->assertSame(2000, $order->getTotalGross()->getCents());
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

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }
}
