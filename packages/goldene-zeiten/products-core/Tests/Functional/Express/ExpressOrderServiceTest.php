<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\PaymentTransactionRepository;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Event\BeforeOrderPlacedEvent;
use GoldeneZeiten\Products\Core\Express\Exception\EmptyExpressBasketException;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressAmountMismatchException;
use GoldeneZeiten\Products\Core\Express\Exception\ExpressOrderFailedException;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Express\ExpressOrderService;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InsufficientStockException;
use GoldeneZeiten\Products\Core\Service\Order\Exception\OrderPlacementVetoedException;
use GoldeneZeiten\Products\Core\Shipping\Exception\MissingShippingOptionException;
use GoldeneZeiten\Products\Core\Shipping\Exception\NoShippingOptionAvailableException;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\DependencyInjection\Container;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\EventDispatcher\ListenerProvider;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Express order creation reuses the normal order creation and finalization, so the order it produces is a
 * real, numbered, paid order recorded against the express provider. The wallet is only charged once the
 * order exists, for exactly the order total, and a payment whose order then fails is refunded.
 */
final class ExpressOrderServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/express_with_fees.csv');
    }

    #[Test]
    public function theWalletIsChargedTheFullOrderTotalAndTheOrderIsPaid(): void
    {
        $settlement = new RecordingSettlement();

        $order = $this->get(ExpressOrderService::class)->place(
            $this->request(shipping: true),
            $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
            $this->basketViewModel($this->product(1)),
            $this->address(),
            'tablerate:1',
            $settlement
        );

        // 100.00 goods + 5.00 shipping + 3.00 handling fee: not the 105.00 goods-plus-carrier figure.
        $this->assertSame(10800, $order->getTotalGross()->getCents());
        $this->assertSame([10800], array_map(static fn(Money $amount): int => $amount->getCents(), $settlement->charged));
        $this->assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
        $this->assertSame('fixture-express', $order->getPaymentMethod());
        $this->assertNotSame('', $order->getOrderNumber());
        $this->assertSame([], $settlement->refunded);
    }

    #[Test]
    public function theQuoteIsTheAmountThatIsCharged(): void
    {
        $service = $this->get(ExpressOrderService::class);
        $provider = $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express');
        $settlement = new RecordingSettlement();

        $quote = $service->quote($this->request(shipping: true), $provider, $this->basketViewModel($this->product(1)), $this->address(), 'tablerate:1');
        $service->place($this->request(shipping: true), $provider, $this->basketViewModel($this->product(1)), $this->address(), 'tablerate:1', $settlement);

        $this->assertSame($quote->getTotalGross()->getCents(), $settlement->charged[0]->getCents());
    }

    #[Test]
    public function aSettledAmountThatDiffersFromTheOrderTotalIsRefundedAndNoOrderIsKept(): void
    {
        $settlement = new RecordingSettlement(Money::fromCents(10500));

        try {
            $this->get(ExpressOrderService::class)->place(
                $this->request(shipping: true),
                $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
                $this->basketViewModel($this->product(1)),
                $this->address(),
                'tablerate:1',
                $settlement
            );
            $this->fail('Expected ExpressAmountMismatchException was not thrown.');
        } catch (ExpressAmountMismatchException $exception) {
            $this->assertSame(1791724084, $exception->getCode());
        }

        $this->assertCount(1, $settlement->refunded);
        $this->assertSame('settled-1', $settlement->refunded[0]->getExternalId());
        $this->assertCancelledWithStockRestored();
    }

    #[Test]
    public function aSettlementThatFailsUnexpectedlyCancelsTheCommittedOrderAndRestoresStock(): void
    {
        $settlement = new RecordingSettlement(failure: new \RuntimeException('Gateway timeout', 1791727123));

        try {
            $this->get(ExpressOrderService::class)->place(
                $this->request(shipping: true),
                $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
                $this->basketViewModel($this->product(1)),
                $this->address(),
                'tablerate:1',
                $settlement
            );
            $this->fail('Expected ExpressOrderFailedException was not thrown.');
        } catch (ExpressOrderFailedException) {
            // expected
        }

        $this->assertSame([], $settlement->refunded);
        $this->assertCancelledWithStockRestored();
    }

    #[Test]
    public function aPaymentThatIsStillProcessingAcceptsTheOrderWithAPendingPaymentAndBindsItsReference(): void
    {
        $settlement = new RecordingSettlement(pending: true);

        $order = $this->get(ExpressOrderService::class)->place(
            $this->request(shipping: true),
            $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
            $this->basketViewModel($this->product(1)),
            $this->address(),
            'tablerate:1',
            $settlement
        );

        $this->assertSame(PaymentStatus::PENDING, $order->getPaymentStatus());
        $this->assertSame(OrderStatus::CONFIRMED, $order->getStatus());
        $this->assertSame([], $settlement->refunded);
        $transaction = $this->get(PaymentTransactionRepository::class)->findOneByExternalId('fixture-express', 'settled-1');
        $this->assertNotNull($transaction);
        $this->assertSame($order->getUid(), $transaction->getOrderUid());
        $this->assertSame(10800, $transaction->getAmount());
    }

    #[Test]
    public function aPendingPaymentOfADifferentAmountIsRefundedAndNoOrderIsKept(): void
    {
        $settlement = new RecordingSettlement(Money::fromCents(10500), pending: true);

        try {
            $this->get(ExpressOrderService::class)->place(
                $this->request(shipping: true),
                $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
                $this->basketViewModel($this->product(1)),
                $this->address(),
                'tablerate:1',
                $settlement
            );
            $this->fail('Expected ExpressAmountMismatchException was not thrown.');
        } catch (ExpressAmountMismatchException) {
            // expected
        }

        $this->assertCount(1, $settlement->refunded);
    }

    private function assertCancelledWithStockRestored(): void
    {
        $connection = $this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_order');
        $this->assertSame(OrderStatus::CANCELLED->value, $connection->select(['status'], 'tx_products_domain_model_order')->fetchOne());
        $this->assertSame(100, (int)$this->getConnectionPool()->getConnectionForTable('tx_products_domain_model_product')->select(['in_stock'], 'tx_products_domain_model_product', ['uid' => 1])->fetchOne());
    }

    #[Test]
    public function aMissingShippingOptionIsRejectedBeforeAnythingIsCharged(): void
    {
        $settlement = new RecordingSettlement();

        try {
            $this->get(ExpressOrderService::class)->place(
                $this->request(shipping: true),
                $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
                $this->basketViewModel($this->product(1)),
                $this->address(),
                '',
                $settlement
            );
            $this->fail('Expected ExpressOrderFailedException was not thrown.');
        } catch (ExpressOrderFailedException $exception) {
            $this->assertSame(1791724086, $exception->getCode());
            $this->assertInstanceOf(MissingShippingOptionException::class, $exception->getPrevious());
        }

        $this->assertSame([], $settlement->charged);
    }

    #[Test]
    public function anUnknownShippingOptionIsRejectedBeforeAnythingIsCharged(): void
    {
        $settlement = new RecordingSettlement();

        try {
            $this->get(ExpressOrderService::class)->place(
                $this->request(shipping: true),
                $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
                $this->basketViewModel($this->product(1)),
                $this->address(),
                'tablerate:999',
                $settlement
            );
            $this->fail('Expected ExpressOrderFailedException was not thrown.');
        } catch (ExpressOrderFailedException $exception) {
            $this->assertInstanceOf(NoShippingOptionAvailableException::class, $exception->getPrevious());
        }

        $this->assertSame([], $settlement->charged);
    }

    #[Test]
    public function missingStockIsRejectedBeforeAnythingIsCharged(): void
    {
        $settlement = new RecordingSettlement();

        try {
            $this->get(ExpressOrderService::class)->place(
                $this->request(shipping: true),
                $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
                $this->basketViewModel($this->product(2)),
                $this->address(),
                'tablerate:1',
                $settlement
            );
            $this->fail('Expected ExpressOrderFailedException was not thrown.');
        } catch (ExpressOrderFailedException $exception) {
            $this->assertInstanceOf(InsufficientStockException::class, $exception->getPrevious());
        }

        $this->assertSame([], $settlement->charged);
    }

    #[Test]
    public function anIntegratorVetoStopsTheExpressOrderBeforeAnythingIsCharged(): void
    {
        $container = $this->getContainer();
        $this->assertInstanceOf(Container::class, $container);
        $container->set('express-veto-listener', static function (BeforeOrderPlacedEvent $event): void {
            $event->veto('Not for this customer.');
        });
        $container->get(ListenerProvider::class)->addListener(BeforeOrderPlacedEvent::class, 'express-veto-listener');
        $settlement = new RecordingSettlement();

        try {
            $this->get(ExpressOrderService::class)->place(
                $this->request(shipping: true),
                $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
                $this->basketViewModel($this->product(1)),
                $this->address(),
                'tablerate:1',
                $settlement
            );
            $this->fail('Expected ExpressOrderFailedException was not thrown.');
        } catch (ExpressOrderFailedException $exception) {
            $this->assertInstanceOf(OrderPlacementVetoedException::class, $exception->getPrevious());
        }

        $this->assertSame([], $settlement->charged);
    }

    #[Test]
    public function anEmptyBasketIsRejected(): void
    {
        $this->expectException(EmptyExpressBasketException::class);
        $this->expectExceptionCode(1784220769);

        $this->get(ExpressOrderService::class)->place(
            $this->request(shipping: false),
            $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
            new BasketViewModel([], Money::fromCents(0), Money::fromCents(0), Money::fromCents(0), 'EUR'),
            new Address(country: 'DE'),
            '',
            new RecordingSettlement()
        );
    }

    private function request(bool $shipping): ServerRequestInterface
    {
        $site = new Site('products', 1, ['settings' => ['products' => [
            'shipping' => ['enabled' => $shipping],
            'handling' => ['enabled' => $shipping],
        ]]]);

        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $site);
    }

    private function address(): Address
    {
        return new Address(email: 'wallet@example.com', firstName: 'Wallet', lastName: 'Buyer', street: 'Wallet St 1', zip: '10115', city: 'Berlin', country: 'DE');
    }

    private function product(int $uid): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid($uid);
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
}
