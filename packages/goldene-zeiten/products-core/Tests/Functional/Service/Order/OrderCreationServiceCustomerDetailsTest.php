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
use GoldeneZeiten\Products\Core\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * The contact and company data, the comment and the desired delivery date the customer entered in the
 * checkout end up on the order the shop fulfils and invoices from.
 */
final class OrderCreationServiceCustomerDetailsTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/order_placement.csv');
    }

    #[Test]
    public function contactAndCompanyDataOfBothAddressesAreSnapshotted(): void
    {
        $subject = $this->get(OrderCreationService::class);
        $delivery = new Address(
            salutation: 'mr',
            firstName: 'John',
            lastName: 'Receiver',
            company: 'Receiver GmbH',
            street: 'Harbour Road 7',
            zip: '54321',
            city: 'Portcity',
            country: 'AT',
            telephone: '+43 1 999',
        );

        $order = $subject->create(
            $this->request(),
            $this->basketViewModel(),
            new CheckoutSelections('', $delivery),
            new Address(
                email: 'buyer@example.com',
                salutation: 'ms',
                firstName: 'Jane',
                lastName: 'Doe',
                company: 'Goldschmiede Muster',
                street: 'Main Street 1',
                zip: '10115',
                city: 'Berlin',
                country: 'DE',
                telephone: '+49 30 123456',
                vatId: 'DE123456789',
            ),
            $this->get(PaymentMethodRegistry::class)->get('invoice')
        );

        $billing = $order->getBillingAddress();
        $this->assertNotNull($billing);
        $this->assertSame('ms', $billing->getSalutation());
        $this->assertSame('Goldschmiede Muster', $billing->getCompany());
        $this->assertSame('+49 30 123456', $billing->getTelephone());
        $this->assertSame('DE123456789', $billing->getVatId());
        $deliveryAddress = $order->getDeliveryAddress();
        $this->assertNotNull($deliveryAddress);
        $this->assertSame('Receiver GmbH', $deliveryAddress->getCompany());
        $this->assertSame('+43 1 999', $deliveryAddress->getTelephone());
    }

    #[Test]
    public function theCommentAndTheDesiredDeliveryDateAreStoredOnTheOrder(): void
    {
        $subject = $this->get(OrderCreationService::class);

        $order = $subject->create(
            $this->request(),
            $this->basketViewModel(),
            new CheckoutSelections(customerNote: 'Please call before delivery.', desiredDeliveryDate: new \DateTimeImmutable('2030-12-23')),
            new Address(email: 'buyer@example.com', country: 'DE'),
            $this->get(PaymentMethodRegistry::class)->get('invoice')
        );

        $this->assertSame('Please call before delivery.', $order->getCustomerNote());
        $this->assertSame('2030-12-23', $order->getDesiredDeliveryDate()?->format('Y-m-d'));
    }

    private function request(): ServerRequest
    {
        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
    }

    private function basketViewModel(): BasketViewModel
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
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
}
