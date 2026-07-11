<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
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
use GoldeneZeiten\Products\Service\Shipping\Exception\NoShippingMethodAvailableException;
use GoldeneZeiten\Products\Service\Shipping\HandlingFeeService;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderCreationServiceShippingTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_placement_with_shipping.csv');
        // OrderFactory reads Extbase settings eagerly in its constructor, which requires a request
        // to be resolvable via $GLOBALS['TYPO3_REQUEST'] outside of a real controller dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        $this->product = $product;
    }

    #[Test]
    public function aChosenAvailableMethodAddsItsRateToTheOrderTotal(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame(1, $order->getShippingMethod());
        self::assertSame(500, $order->getShippingTotal()->getCents());
        self::assertSame(10500, $order->getTotalGross()->getCents());
    }

    #[Test]
    public function aFreeShippingVoucherZeroesTheShippingCost(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel(),
            new CheckoutSelections(['FREESHIP'], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame(1, $order->getShippingMethod());
        self::assertSame(0, $order->getShippingTotal()->getCents());
    }

    #[Test]
    public function aRegularVoucherDoesNotWaiveTheShippingCost(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel(),
            new CheckoutSelections(['REGULAR'], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame(500, $order->getShippingTotal()->getCents());
    }

    #[Test]
    public function shippingIsANoOpWhileTheFeatureIsDisabledEvenWithAMethodChosen(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: false),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame(0, $order->getShippingMethod());
        self::assertSame(0, $order->getShippingTotal()->getCents());
        self::assertSame(10000, $order->getTotalGross()->getCents());
    }

    #[Test]
    public function shippingIsANoOpWhenNoMethodWasChosen(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 0),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame(0, $order->getShippingMethod());
        self::assertSame(0, $order->getShippingTotal()->getCents());
    }

    #[Test]
    public function placementFailsWithoutSideEffectsWhenTheChosenMethodIsNoLongerAvailable(): void
    {
        $orderCountBefore = $this->countOrders();
        $stockBefore = $this->currentStock();

        try {
            $this->subject()->create(
                $this->requestWithShipping(enabled: true),
                $this->basketViewModel(),
                new CheckoutSelections([], 0, 999),
                $this->address(),
                $this->paymentMethod()
            );
            self::fail('Expected NoShippingMethodAvailableException was not thrown.');
        } catch (NoShippingMethodAvailableException) {
            // expected
        }

        self::assertSame($orderCountBefore, $this->countOrders());
        self::assertSame($stockBefore, $this->currentStock());
    }

    private function subject(): OrderCreationService
    {
        return new OrderCreationService(
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
            $this->get(ShippingCostService::class),
            $this->get(HandlingFeeService::class),
            $this->get(ConfigurationManagerInterface::class),
            $this->get(ProductsConfigurationFactory::class)
        );
    }

    private function requestWithShipping(bool $enabled): ServerRequestInterface
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['shipping' => ['enabled' => $enabled]]]]);
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $site);
    }

    private function basketViewModel(): BasketViewModel
    {
        $unitPriceNet = Money::fromDecimalString('84.03');
        $unitPriceGross = Money::fromDecimalString('100.00');
        $item = new BasketViewItem(
            $this->product,
            null,
            1,
            $unitPriceNet,
            $unitPriceGross,
            0.19,
            $unitPriceNet,
            $unitPriceGross,
            $unitPriceGross->subtract($unitPriceNet)
        );
        return new BasketViewModel([$item], $unitPriceNet, $unitPriceGross, $unitPriceGross->subtract($unitPriceNet), 'EUR');
    }

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }

    private function paymentMethod(): PaymentMethodInterface
    {
        return $this->get(PaymentMethodRegistry::class)->get('invoice');
    }

    private function countOrders(): int
    {
        return (int)$this->getConnectionPool()
            ->getConnectionForTable('tx_products_domain_model_order')
            ->executeQuery('SELECT COUNT(*) FROM tx_products_domain_model_order')
            ->fetchOne();
    }

    private function currentStock(): int
    {
        $this->get(PersistenceManagerInterface::class)->clearState();
        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        return $product->getInStock();
    }
}
