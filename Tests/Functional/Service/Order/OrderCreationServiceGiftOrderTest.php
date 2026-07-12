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
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

final class OrderCreationServiceGiftOrderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/order_placement_with_voucher.csv');
        // OrderFactory reads Extbase settings eagerly in its constructor, which requires a request
        // to be resolvable via $GLOBALS['TYPO3_REQUEST'] outside of a real controller dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    #[Test]
    public function anOrderWithoutAGiftChoiceStaysBillingOnly(): void
    {
        $subject = $this->get(OrderCreationService::class);

        $order = $subject->create(
            $this->request(),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertNull($order->getDeliveryAddress());
        $this->assertSame('', $order->getGiftMessage());
    }

    #[Test]
    public function anAlternateDeliveryAddressIsSnapshottedOntoTheOrder(): void
    {
        $subject = $this->get(OrderCreationService::class);
        $delivery = new Address(firstName: 'Jane', lastName: 'Doe', street: 'Gift Lane 1', zip: '54321', city: 'Giftville', country: 'AT');

        $order = $subject->create(
            $this->request(),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 0, $delivery),
            $this->address(),
            $this->paymentMethod()
        );

        $deliveryAddress = $order->getDeliveryAddress();
        $this->assertNotNull($deliveryAddress);
        $this->assertSame('delivery', $deliveryAddress->getAddressType());
        $this->assertSame('Jane', $deliveryAddress->getFirstName());
        $this->assertSame('Doe', $deliveryAddress->getLastName());
        $this->assertSame('Gift Lane 1', $deliveryAddress->getStreet());
        $this->assertSame('54321', $deliveryAddress->getZip());
        $this->assertSame('Giftville', $deliveryAddress->getCity());
        $this->assertSame('AT', $deliveryAddress->getCountry());
    }

    #[Test]
    public function billingAddressIsUnaffectedByAnAlternateDeliveryAddress(): void
    {
        $subject = $this->get(OrderCreationService::class);
        $delivery = new Address(firstName: 'Jane', lastName: 'Doe', country: 'AT');

        $order = $subject->create(
            $this->request(),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 0, $delivery),
            $this->address(),
            $this->paymentMethod()
        );

        $billingAddress = $order->getBillingAddress();
        $this->assertNotNull($billingAddress);
        $this->assertSame('billing', $billingAddress->getAddressType());
        $this->assertSame('DE', $billingAddress->getCountry());
    }

    #[Test]
    public function aGiftMessageIsPersistedOntoTheOrder(): void
    {
        $subject = $this->get(OrderCreationService::class);

        $order = $subject->create(
            $this->request(),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 0, null, 'Happy birthday!'),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame('Happy birthday!', $order->getGiftMessage());
    }

    private function request(): ServerRequestInterface
    {
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
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
