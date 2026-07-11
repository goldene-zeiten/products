<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Service\Shipping\Exception\NoShippingMethodAvailableException;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderCreationServiceShippingTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_placement_with_shipping.csv');
    }

    #[Test]
    public function aChosenAvailableMethodAddsItsRateToTheOrderTotal(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame(1, $order->getShippingMethod());
        $this->assertSame(500, $order->getShippingTotal()->getCents());
        $this->assertSame(10500, $order->getTotalGross()->getCents());
    }

    #[Test]
    public function aFreeShippingVoucherZeroesTheShippingCost(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel($this->product()),
            new CheckoutSelections(['FREESHIP'], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame(1, $order->getShippingMethod());
        $this->assertSame(0, $order->getShippingTotal()->getCents());
    }

    #[Test]
    public function aRegularVoucherDoesNotWaiveTheShippingCost(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel($this->product()),
            new CheckoutSelections(['REGULAR'], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame(500, $order->getShippingTotal()->getCents());
    }

    #[Test]
    public function shippingIsANoOpWhileTheFeatureIsDisabledEvenWithAMethodChosen(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: false),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame(0, $order->getShippingMethod());
        $this->assertSame(0, $order->getShippingTotal()->getCents());
        $this->assertSame(10000, $order->getTotalGross()->getCents());
    }

    #[Test]
    public function shippingIsANoOpWhenNoMethodWasChosen(): void
    {
        $order = $this->subject()->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 0),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame(0, $order->getShippingMethod());
        $this->assertSame(0, $order->getShippingTotal()->getCents());
    }

    #[Test]
    public function placementFailsWithoutSideEffectsWhenTheChosenMethodIsNoLongerAvailable(): void
    {
        $orderCountBefore = $this->countOrders();
        $stockBefore = $this->currentStock();

        try {
            $this->subject()->create(
                $this->requestWithShipping(enabled: true),
                $this->basketViewModel($this->product()),
                new CheckoutSelections([], 0, 999),
                $this->address(),
                $this->paymentMethod()
            );
            $this->fail('Expected NoShippingMethodAvailableException was not thrown.');
        } catch (NoShippingMethodAvailableException) {
            // expected
        }

        $this->assertSame($orderCountBefore, $this->countOrders());
        $this->assertSame($stockBefore, $this->currentStock());
    }

    private function subject(): OrderCreationService
    {
        return $this->get(OrderCreationService::class);
    }

    private function requestWithShipping(bool $enabled): ServerRequestInterface
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['shipping' => ['enabled' => $enabled]]]]);
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $site);
    }

    private function product(): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }

    private function basketViewModel(Product $product): BasketViewModel
    {
        $unitPriceNet = Money::fromDecimalString('84.03');
        $unitPriceGross = Money::fromDecimalString('100.00');
        $item = new BasketViewItem(
            $product,
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
        $this->assertInstanceOf(Product::class, $product);
        return $product->getInStock();
    }
}
