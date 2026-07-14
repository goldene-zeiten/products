<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Core\Shipping\Exception\NoShippingOptionAvailableException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

final class OrderCreationServiceShippingTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderCreationServiceShippingTest/order_placement_with_shipping.csv');
    }

    /**
     * @param string[] $voucherCodes
     */
    #[Test]
    #[DataProvider('shippingMethodVoucherScenarioProvider')]
    public function shippingMethodAndVoucherHandling(array $voucherCodes, int $expectedShippingCents, int $expectedTotalGross = 10500): void
    {
        $subject = $this->subject();

        $order = $subject->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel($this->product()),
            new CheckoutSelections($voucherCodes, 0, 'tablerate:1'),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame('tablerate', $order->getShippingProvider());
        $this->assertSame('1', $order->getShippingOption());
        $this->assertSame($expectedShippingCents, $order->getShippingTotal()->getCents());
        $this->assertSame($expectedTotalGross, $order->getTotalGross()->getCents());
    }

    /**
     * @return \Generator<string, array<string, mixed>>
     */
    public static function shippingMethodVoucherScenarioProvider(): \Generator
    {
        yield 'chosenMethodAddsItsRate' => ['voucherCodes' => [], 'expectedShippingCents' => 500, 'expectedTotalGross' => 10500];
        yield 'freeShippingVoucherOffsetsTheCostInsteadOfHidingIt' => ['voucherCodes' => ['FREESHIP'], 'expectedShippingCents' => 500, 'expectedTotalGross' => 10000];
        yield 'regularVoucherDoesNotWaiveTheShipping' => ['voucherCodes' => ['REGULAR'], 'expectedShippingCents' => 500, 'expectedTotalGross' => 10500];
    }

    #[Test]
    public function shippingIsANoOpWhileTheFeatureIsDisabledEvenWithAMethodChosen(): void
    {
        $subject = $this->subject();

        $order = $subject->create(
            $this->requestWithShipping(enabled: false),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 'tablerate:1'),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame('', $order->getShippingProvider());
        $this->assertSame(0, $order->getShippingTotal()->getCents());
        $this->assertSame(10000, $order->getTotalGross()->getCents());
    }

    #[Test]
    public function shippingIsANoOpWhenNoMethodWasChosen(): void
    {
        $subject = $this->subject();

        $order = $subject->create(
            $this->requestWithShipping(enabled: true),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, ''),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame('', $order->getShippingProvider());
        $this->assertSame(0, $order->getShippingTotal()->getCents());
    }

    #[Test]
    public function placementFailsWithoutSideEffectsWhenTheChosenMethodIsNoLongerAvailable(): void
    {
        $subject = $this->subject();

        try {
            $subject->create(
                $this->requestWithShipping(enabled: true),
                $this->basketViewModel($this->product()),
                new CheckoutSelections([], 0, 'tablerate:999'),
                $this->address(),
                $this->paymentMethod()
            );
            $this->fail('Expected NoShippingOptionAvailableException was not thrown.');
        } catch (NoShippingOptionAvailableException) {
            // expected
        }

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/shipping_unavailable_no_side_effects.csv');
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
}
