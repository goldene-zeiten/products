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
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

final class OrderCreationServiceGiftOrderTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private OrderCreationService $subject;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_placement_with_voucher.csv');
        // OrderFactory reads Extbase settings eagerly in its constructor, which requires a request
        // to be resolvable via $GLOBALS['TYPO3_REQUEST'] outside of a real controller dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $this->subject = $this->get(OrderCreationService::class);
        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        $this->product = $product;
    }

    #[Test]
    public function anOrderWithoutAGiftChoiceStaysBillingOnly(): void
    {
        $order = $this->subject->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel(),
            new CheckoutSelections([], 0),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertNull($order->getDeliveryAddress());
        self::assertSame('', $order->getGiftMessage());
    }

    #[Test]
    public function anAlternateDeliveryAddressIsSnapshottedOntoTheOrder(): void
    {
        $delivery = new Address(firstName: 'Jane', lastName: 'Doe', street: 'Gift Lane 1', zip: '54321', city: 'Giftville', country: 'AT');

        $order = $this->subject->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 0, $delivery),
            $this->address(),
            $this->paymentMethod()
        );

        $deliveryAddress = $order->getDeliveryAddress();
        self::assertNotNull($deliveryAddress);
        self::assertSame('delivery', $deliveryAddress->getAddressType());
        self::assertSame('Jane', $deliveryAddress->getFirstName());
        self::assertSame('Doe', $deliveryAddress->getLastName());
        self::assertSame('Gift Lane 1', $deliveryAddress->getStreet());
        self::assertSame('54321', $deliveryAddress->getZip());
        self::assertSame('Giftville', $deliveryAddress->getCity());
        self::assertSame('AT', $deliveryAddress->getCountry());
    }

    #[Test]
    public function billingAddressIsUnaffectedByAnAlternateDeliveryAddress(): void
    {
        $delivery = new Address(firstName: 'Jane', lastName: 'Doe', country: 'AT');

        $order = $this->subject->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 0, $delivery),
            $this->address(),
            $this->paymentMethod()
        );

        $billingAddress = $order->getBillingAddress();
        self::assertNotNull($billingAddress);
        self::assertSame('billing', $billingAddress->getAddressType());
        self::assertSame('DE', $billingAddress->getCountry());
    }

    #[Test]
    public function aGiftMessageIsPersistedOntoTheOrder(): void
    {
        $order = $this->subject->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 0, null, 'Happy birthday!'),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame('Happy birthday!', $order->getGiftMessage());
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
}
