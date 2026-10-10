<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\CheckoutChoices;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Payment\Exception\PaymentMethodNotAvailableException;
use GoldeneZeiten\Products\Core\Service\Basket\BasketService;
use GoldeneZeiten\Products\Core\Service\Order\Exception\IncompleteAddressException;
use GoldeneZeiten\Products\Core\Service\Order\OrderPlacementService;
use GoldeneZeiten\Products\Core\Shipping\Exception\MissingShippingOptionException;
use GoldeneZeiten\Products\Core\Shipping\Exception\NoShippingOptionAvailableException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * The checkout steps store whatever the customer submits, so placement re-checks every choice against
 * what the shop would actually offer for this basket and customer before an order is created.
 */
final class OrderPlacementValidationTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderPlacementValidationTest/order_placement.csv');
    }

    #[Test]
    #[DataProvider('unofferedPaymentMethodProvider')]
    public function placeRejectsAPaymentMethodThatIsNotOffered(string $paymentMethod): void
    {
        $request = $this->requestWithBasket();

        try {
            $this->get(OrderPlacementService::class)->place($request, $this->address(), $paymentMethod, new CheckoutChoices(termsAccepted: true));
            $this->fail('Expected PaymentMethodNotAvailableException was not thrown.');
        } catch (PaymentMethodNotAvailableException $exception) {
            $this->assertSame(1791662739, $exception->getCode());
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    public static function unofferedPaymentMethodProvider(): \Generator
    {
        yield 'registered method that is unavailable for the basket' => [
            'paymentMethod' => 'fixture-unavailable',
        ];
        yield 'unregistered method' => [
            'paymentMethod' => 'no-such-method',
        ];
    }

    #[Test]
    public function placeAcceptsAnOfferedPaymentMethod(): void
    {
        $request = $this->requestWithBasket();

        $order = $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice', new CheckoutChoices(termsAccepted: true))->getOrder();

        $this->assertSame('invoice', $order->getPaymentMethod());
    }

    #[Test]
    public function placeRejectsAMissingShippingOptionWhileShippingIsEnabled(): void
    {
        $request = $this->requestWithBasket(shippingEnabled: true);

        try {
            $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice', new CheckoutChoices(termsAccepted: true));
            $this->fail('Expected MissingShippingOptionException was not thrown.');
        } catch (MissingShippingOptionException $exception) {
            $this->assertSame(1791662975, $exception->getCode());
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    #[Test]
    public function placeRejectsAShippingOptionThatIsNotOffered(): void
    {
        $request = $this->requestWithBasket(shippingEnabled: true);

        try {
            $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice', new CheckoutChoices(shippingOptionKey: 'tablerate:999', termsAccepted: true));
            $this->fail('Expected NoShippingOptionAvailableException was not thrown.');
        } catch (NoShippingOptionAvailableException) {
            // expected
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    #[Test]
    public function placeChargesAnOfferedShippingOption(): void
    {
        $request = $this->requestWithBasket(shippingEnabled: true);

        $order = $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice', new CheckoutChoices(shippingOptionKey: 'tablerate:1', termsAccepted: true))->getOrder();

        $this->assertSame(500, $order->getShippingTotal()->getCents());
    }

    #[Test]
    #[DataProvider('incompleteAddressProvider')]
    public function placeRejectsAnIncompleteAddress(Address $address, ?Address $deliveryAddress): void
    {
        $request = $this->requestWithBasket();

        try {
            $this->get(OrderPlacementService::class)->place($request, $address, 'invoice', new CheckoutChoices(deliveryAddress: $deliveryAddress, termsAccepted: true));
            $this->fail('Expected IncompleteAddressException was not thrown.');
        } catch (IncompleteAddressException $exception) {
            $this->assertSame(1791663303, $exception->getCode());
        }

        $this->assertSame(0, $this->get(OrderRepository::class)->findAll()->count());
    }

    public static function incompleteAddressProvider(): \Generator
    {
        $complete = [
            'email' => 'buyer@example.com',
            'firstName' => 'Jane',
            'lastName' => 'Doe',
            'street' => 'Main Street 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ];
        yield 'checkout session without an address' => [
            'address' => new Address(),
            'deliveryAddress' => null,
        ];
        yield 'invalid email' => [
            'address' => new Address(...['email' => 'not-an-email'] + $complete),
            'deliveryAddress' => null,
        ];
        foreach (['firstName', 'lastName', 'street', 'zip', 'city', 'country'] as $field) {
            yield 'missing ' . $field => [
                'address' => new Address(...[$field => ' '] + $complete),
                'deliveryAddress' => null,
            ];
        }
        yield 'incomplete delivery address' => [
            'address' => new Address(...$complete),
            'deliveryAddress' => new Address(firstName: 'Jane', lastName: 'Doe', country: 'DE'),
        ];
    }

    #[Test]
    public function placeAcceptsADeliveryAddressWithoutAnEmail(): void
    {
        $request = $this->requestWithBasket();
        $deliveryAddress = new Address(firstName: 'John', lastName: 'Doe', street: 'Gift Lane 1', zip: '54321', city: 'Giftville', country: 'DE');

        $order = $this->get(OrderPlacementService::class)->place($request, $this->address(), 'invoice', new CheckoutChoices(deliveryAddress: $deliveryAddress, termsAccepted: true))->getOrder();

        $this->assertNotSame('', $order->getOrderNumber());
    }

    private function requestWithBasket(bool $shippingEnabled = false): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $frontendUser->user = ['uid' => 0];
        $site = new Site('products', 1, ['settings' => ['products' => ['shipping' => ['enabled' => $shippingEnabled]]]]);
        $request = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser)
            ->withAttribute('site', $site);
        $this->get(BasketService::class)->add($request, 1, null, 1);

        return $request;
    }

    private function address(): Address
    {
        return new Address(
            email: 'buyer@example.com',
            firstName: 'Jane',
            lastName: 'Doe',
            street: 'Main Street 1',
            zip: '10115',
            city: 'Berlin',
            country: 'DE'
        );
    }
}
