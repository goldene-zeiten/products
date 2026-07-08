<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\EndToEnd;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\CreditPointsTransactionRepository;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\CreditPoints\Exception\InsufficientCreditPointsException;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Service\Order\OrderFactory;
use GoldeneZeiten\Products\Service\Order\OrderFinalizationService;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Service\Order\OrderPlacementTransaction;
use GoldeneZeiten\Products\Service\Order\PaymentInitiationService;
use GoldeneZeiten\Products\Service\Order\StockService;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * End-to-end coverage of the M3 checkout flow (per the M3 plan's verification section): a
 * related-product upsell visible on the basket, a combinable voucher replaced by a non-combinable
 * one, a partial credit-points spend, and a guest checkout that is rejected for the points step but
 * still completes for the voucher.
 */
final class M3CheckoutFlowTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private OrderPlacementService $orderPlacementService;
    private BasketService $basketService;
    private Product $mainProduct;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/m3_end_to_end.csv');
        // OrderFactory/TaxService read Extbase settings eagerly in their constructors, which
        // requires a request resolvable via $GLOBALS['TYPO3_REQUEST'] outside a real dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);

        $this->basketService = $this->get(BasketService::class);
        $this->orderPlacementService = $this->buildOrderPlacementService();

        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        $this->mainProduct = $product;
    }

    #[Test]
    public function identifiedCustomerReplacesVoucherAndSpendsPartialPoints(): void
    {
        $request = $this->requestFor(7);
        $this->basketService->add($request, $this->mainProduct->getUid() ?? 0, null, 1);

        $relatedTitles = array_map(
            static fn(Product $related): string => $related->getTitle(),
            $this->mainProduct->getRelatedProducts()->toArray()
        );
        self::assertSame(['Related Product'], $relatedTitles);

        $this->applyVoucher($request, 'COMBO1');
        self::assertSame(['COMBO1'], $this->basketService->getAppliedVoucherCodes($request));

        $this->applyVoucher($request, 'SOLO');
        self::assertSame(['SOLO'], $this->basketService->getAppliedVoucherCodes($request), 'A non-combinable voucher must replace, not join, an existing code.');

        $order = $this->orderPlacementService->place($request, $this->address(), 'invoice', new CheckoutChoices(30))->getOrder();

        self::assertSame(9200, $order->getTotalGross()->getCents());
        self::assertSame(800, $order->getDiscountTotal()->getCents());
        self::assertSame(['SOLO'], $order->getVoucherCodes());

        $voucher = $this->get(VoucherRepository::class)->findOneByCode('SOLO');
        self::assertNotNull($voucher);
        self::assertSame(1, $this->get(VoucherRedemptionRepository::class)->countFor($voucher));

        $ledgerRows = $this->ledgerRows($order->getUid() ?? 0);
        self::assertCount(2, $ledgerRows);
        self::assertContainsEquals(['frontend_user' => 7, 'points' => 10, 'type' => 'earn'], $ledgerRows);
        self::assertContainsEquals(['frontend_user' => 7, 'points' => -30, 'type' => 'redeem'], $ledgerRows);

        self::assertSame([], $this->basketService->getAppliedVoucherCodes($request), 'A finalized order clears the basket, including its voucher codes.');
    }

    #[Test]
    public function guestCheckoutIsRejectedForPointsButStillCompletesForTheVoucher(): void
    {
        $request = $this->requestFor(0);
        $this->basketService->add($request, $this->mainProduct->getUid() ?? 0, null, 1);
        $this->applyVoucher($request, 'COMBO1');

        $orderCountBefore = $this->countOrders();
        try {
            $this->orderPlacementService->place($request, $this->address(), 'invoice', new CheckoutChoices(10));
            self::fail('Expected InsufficientCreditPointsException was not thrown for a guest requesting points.');
        } catch (InsufficientCreditPointsException) {
            // expected: guests always have a zero balance
        }
        self::assertSame($orderCountBefore, $this->countOrders(), 'A rejected points request must not place an order.');

        $order = $this->orderPlacementService->place($request, $this->address(), 'invoice')->getOrder();

        self::assertSame(9000, $order->getTotalGross()->getCents());
        self::assertSame(1000, $order->getDiscountTotal()->getCents());
        self::assertSame(['COMBO1'], $order->getVoucherCodes());
        self::assertSame([], $this->ledgerRows($order->getUid() ?? 0), 'Guests never touch the credit points ledger.');
    }

    private function applyVoucher(ServerRequestInterface $request, string $code): void
    {
        $voucherService = $this->get(VoucherService::class);
        $frontendUser = $this->get(FrontendUserResolver::class)->getUid($request);
        $basketGoodsTotal = $this->basketService->getBasketViewModel($request)->getTotalGross();

        $newVoucher = $voucherService->resolve($code, $basketGoodsTotal, $frontendUser);
        $existingCodes = $this->basketService->getAppliedVoucherCodes($request);
        $existingVouchers = $voucherService->buildDiscountSummary($existingCodes, $basketGoodsTotal, $frontendUser)->getAppliedVouchers();
        if (!$voucherService->canCoexist($existingVouchers, $newVoucher)) {
            $this->basketService->clearVoucherCodes($request);
        }
        $this->basketService->addVoucherCode($request, $newVoucher->getCode());
    }

    private function buildOrderPlacementService(): OrderPlacementService
    {
        $creditPointsService = new CreditPointsService($this->get(ConnectionPool::class), $this->fakeConfigurationManager());
        $orderCreationService = new OrderCreationService(
            $this->get(StockService::class),
            $this->get(OrderRepository::class),
            $this->get(OrderFactory::class),
            $this->get(PersistenceManagerInterface::class),
            $this->get(EventDispatcherInterface::class),
            $this->get(VoucherService::class),
            $this->get(VoucherRedemptionRepository::class),
            $creditPointsService,
            $this->get(CreditPointsTransactionRepository::class),
            $this->get(FrontendUserResolver::class),
            $this->get(ShippingCostService::class),
            $this->get(ConfigurationManagerInterface::class)
        );
        $orderPlacementTransaction = new OrderPlacementTransaction(
            $this->get(ConnectionPool::class),
            $orderCreationService,
            $this->get(PaymentInitiationService::class)
        );
        return new OrderPlacementService(
            $this->basketService,
            $this->get(PaymentMethodRegistry::class),
            $orderPlacementTransaction,
            $this->get(OrderFinalizationService::class),
            $this->get(EventDispatcherInterface::class),
            $creditPointsService,
            $this->get(FrontendUserResolver::class)
        );
    }

    private function fakeConfigurationManager(): ConfigurationManagerInterface
    {
        return new class () implements ConfigurationManagerInterface {
            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['creditPoints' => ['enabled' => true, 'moneyPerPoint' => '0.10']];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
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

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ledgerRows(int $orderUid): array
    {
        return $this->getConnectionPool()
            ->getConnectionForTable('tx_products_domain_model_creditpointstransaction')
            ->select(['frontend_user', 'points', 'type'], 'tx_products_domain_model_creditpointstransaction', ['order_uid' => $orderUid])
            ->fetchAllAssociative();
    }

    private function countOrders(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_products_domain_model_order')
            ->executeQuery('SELECT COUNT(*) FROM tx_products_domain_model_order')
            ->fetchOne();
    }
}
