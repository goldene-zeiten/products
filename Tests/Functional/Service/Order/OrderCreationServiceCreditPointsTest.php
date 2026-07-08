<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\CreditPointsTransactionRepository;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Service\Order\OrderFactory;
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
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class OrderCreationServiceCreditPointsTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_placement_with_credit_points.csv');
        // OrderFactory reads Extbase settings eagerly in its constructor, which requires a request
        // to be resolvable via $GLOBALS['TYPO3_REQUEST'] outside of a real controller dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        $this->product = $product;
    }

    #[Test]
    public function identifiedCustomerEarnsAndRedeemsPointsOnPlacement(): void
    {
        $order = $this->subject(enabled: true)->create(
            $this->requestFor(5),
            $this->basketViewModel(),
            new CheckoutSelections([], 20),
            $this->address(),
            $this->paymentMethod()
        );

        $rows = $this->ledgerRows($order->getUid() ?? 0);
        self::assertCount(2, $rows);
        self::assertContainsEquals(['frontend_user' => 5, 'points' => 20, 'type' => 'earn'], $rows);
        self::assertContainsEquals(['frontend_user' => 5, 'points' => -20, 'type' => 'redeem'], $rows);
        self::assertSame(19800, $order->getTotalGross()->getCents());
        self::assertSame(200, $order->getDiscountTotal()->getCents());
    }

    #[Test]
    public function guestOrdersNeverTouchTheLedgerEvenThoughTheProductEarnsPoints(): void
    {
        $order = $this->subject(enabled: true)->create(
            $this->requestFor(0),
            $this->basketViewModel(),
            new CheckoutSelections([], 0),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame([], $this->ledgerRows($order->getUid() ?? 0));
    }

    #[Test]
    public function nothingIsRecordedOrDiscountedWhileTheFeatureIsDisabled(): void
    {
        $order = $this->subject(enabled: false)->create(
            $this->requestFor(5),
            $this->basketViewModel(),
            new CheckoutSelections([], 20),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame([], $this->ledgerRows($order->getUid() ?? 0));
        self::assertSame(0, $order->getDiscountTotal()->getCents());
    }

    private function subject(bool $enabled): OrderCreationService
    {
        $creditPointsService = new CreditPointsService($this->get(ConnectionPool::class), $this->fakeConfigurationManager($enabled));
        return new OrderCreationService(
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
    }

    private function fakeConfigurationManager(bool $enabled): ConfigurationManagerInterface
    {
        return new class ($enabled) implements ConfigurationManagerInterface {
            public function __construct(private readonly bool $enabled) {}

            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['creditPoints' => ['enabled' => $this->enabled, 'moneyPerPoint' => '0.10']];
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
        $request = new ServerRequest('http://localhost/');
        if ($frontendUserUid === 0) {
            return $request;
        }
        $frontendUser = new FrontendUserAuthentication();
        $frontendUser->user = ['uid' => $frontendUserUid];
        return $request->withAttribute('frontend.user', $frontendUser);
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

    private function basketViewModel(): BasketViewModel
    {
        $unitPrice = Money::fromDecimalString('100.00');
        $lineTotal = Money::fromDecimalString('200.00');
        $item = new BasketViewItem($this->product, null, 2, $unitPrice, $unitPrice, 0.0, $lineTotal, $lineTotal, Money::fromCents(0));
        return new BasketViewModel([$item], $lineTotal, $lineTotal, Money::fromCents(0), 'EUR');
    }

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }

    private function paymentMethod(): PaymentMethodInterface
    {
        return $this->get(PaymentMethodRegistry::class)->get('invoice');
    }
}
