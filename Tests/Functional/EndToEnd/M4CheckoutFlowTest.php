<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\EndToEnd;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Domain\Model\Order;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\CreditPointsTransactionRepository;
use GoldeneZeiten\Products\Domain\Repository\GainedVoucherRepository;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\ShippingMethodRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\EventListener\IssueGainedVoucherListener;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Basket\BasketService;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Service\Order\OrderFactory;
use GoldeneZeiten\Products\Service\Order\OrderFinalizationService;
use GoldeneZeiten\Products\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Service\Order\OrderPlacementTransaction;
use GoldeneZeiten\Products\Service\Order\PaymentInitiationService;
use GoldeneZeiten\Products\Service\Order\StockService;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use GoldeneZeiten\Products\Service\Voucher\GainedVoucherService;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * End-to-end coverage of the M4 checkout flow: a shipping method chosen, a free-shipping voucher
 * applied, an alternate delivery address with a gift message, and a "gained" bonus voucher issued
 * afterward for clearing the reward threshold.
 */
final class M4CheckoutFlowTest extends AbstractFunctionalTestCase
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
        $this->importCSVDataSet(__DIR__ . '/../Fixtures/m4_end_to_end.csv');
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
    public function shippingIsWaivedByAFreeShippingVoucherAndTheGiftAddressIsSnapshotted(): void
    {
        $request = $this->requestFor(9);
        $this->basketService->add($request, $this->mainProduct->getUid() ?? 0, null, 1);
        $this->basketService->addVoucherCode($request, 'FREESHIP');

        $delivery = new Address(firstName: 'Jane', lastName: 'Doe', street: 'Gift Lane 1', zip: '54321', city: 'Giftville', country: 'DE');
        $choices = new CheckoutChoices(spendPoints: 0, shippingMethodUid: 1, deliveryAddress: $delivery, giftMessage: 'Happy birthday!');

        $order = $this->orderPlacementService->place($request, $this->address(), 'invoice', $choices)->getOrder();

        self::assertSame(1, $order->getShippingMethod());
        self::assertSame(0, $order->getShippingTotal()->getCents(), 'The free-shipping voucher must waive the shipping cost.');
        self::assertSame(['FREESHIP'], $order->getVoucherCodes());

        $deliveryAddress = $order->getDeliveryAddress();
        self::assertNotNull($deliveryAddress);
        self::assertSame('Jane', $deliveryAddress->getFirstName());
        self::assertSame('Happy birthday!', $order->getGiftMessage());

        $this->issueGainedVoucherFor($order);

        $voucher = $this->get(VoucherRepository::class)->findOneByCode($this->gainedVoucherCodeFor($order->getUid() ?? 0));
        self::assertNotNull($voucher, 'A gained voucher must have been issued for clearing the reward threshold.');
        self::assertFalse($voucher->isCombinable());
        self::assertSame(1, $voucher->getUsageLimit());
    }

    private function issueGainedVoucherFor(Order $order): void
    {
        $listener = new IssueGainedVoucherListener($this->buildGainedVoucherService(), new NullLogger());
        $listener(new AfterOrderPlacedEvent($order, new ServerRequest('http://localhost/')));
    }

    private function gainedVoucherCodeFor(int $orderUid): string
    {
        $row = $this->getConnectionPool()
            ->getConnectionForTable('tx_products_domain_model_voucher')
            ->select(['code'], 'tx_products_domain_model_voucher', ['generated_from_order' => $orderUid])
            ->fetchAssociative();
        self::assertIsArray($row, 'No gained voucher row was written for this order.');
        return (string)$row['code'];
    }

    private function buildGainedVoucherService(): GainedVoucherService
    {
        return new GainedVoucherService(
            $this->get(GainedVoucherRepository::class),
            $this->get(VoucherRepository::class),
            $this->get(PersistenceManagerInterface::class),
            $this->get(EventDispatcherInterface::class),
            $this->fakeConfigurationManager(['vouchers' => ['gained' => [
                'enabled' => true,
                'minimumOrderValue' => '1.00',
                'rewardType' => 'fixed',
                'rewardValue' => '5.00',
            ]]])
        );
    }

    private function buildOrderPlacementService(): OrderPlacementService
    {
        $shippingCostService = new ShippingCostService(
            $this->get(ShippingMethodRepository::class),
            $this->fakeConfigurationManager(['shipping' => ['enabled' => true]])
        );
        $orderCreationService = new OrderCreationService(
            $this->get(StockService::class),
            $this->get(OrderRepository::class),
            $this->get(OrderFactory::class),
            $this->get(PersistenceManagerInterface::class),
            $this->get(EventDispatcherInterface::class),
            $this->get(VoucherService::class),
            $this->get(VoucherRedemptionRepository::class),
            $this->get(CreditPointsService::class),
            $this->get(CreditPointsTransactionRepository::class),
            $this->get(FrontendUserResolver::class),
            $shippingCostService,
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
            $this->get(CreditPointsService::class),
            $this->get(FrontendUserResolver::class)
        );
    }

    /**
     * @param array<string, mixed> $configuration
     */
    private function fakeConfigurationManager(array $configuration): ConfigurationManagerInterface
    {
        return new class ($configuration) implements ConfigurationManagerInterface {
            /**
             * @param array<string, mixed> $configuration
             */
            public function __construct(
                private readonly array $configuration
            ) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return $this->configuration;
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
}
