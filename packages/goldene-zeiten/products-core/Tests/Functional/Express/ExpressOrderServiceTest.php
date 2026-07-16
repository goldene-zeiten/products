<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\Exception\EmptyExpressBasketException;
use GoldeneZeiten\Products\Core\Express\ExpressCheckoutProviderRegistry;
use GoldeneZeiten\Products\Core\Express\ExpressOrderService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * Express order creation reuses the normal order creation and finalization, so the order it produces is a
 * real, numbered, paid order recorded against the express provider - only the address comes from the
 * wallet and the payment was settled before the order existed.
 */
final class ExpressOrderServiceTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-payment-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Service/Order/Fixtures/order_placement.csv');
    }

    #[Test]
    public function aConfirmedWalletPaymentBecomesAPaidOrder(): void
    {
        $order = $this->get(ExpressOrderService::class)->place(
            $this->request(),
            $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
            $this->basketViewModel($this->product()),
            new Address(email: 'wallet@example.com', firstName: 'Wallet', lastName: 'Buyer', street: 'Wallet St 1', zip: '10115', city: 'Berlin', country: 'DE'),
            '',
            PaymentResult::completed(PaymentStatus::PAID)
        );

        $this->assertNotSame('', $order->getOrderNumber());
        $this->assertSame(PaymentStatus::PAID, $order->getPaymentStatus());
        $this->assertSame('fixture-express', $order->getPaymentMethod());
    }

    #[Test]
    public function anEmptyBasketIsRejected(): void
    {
        $this->expectException(EmptyExpressBasketException::class);
        $this->expectExceptionCode(1784220769);

        $this->get(ExpressOrderService::class)->place(
            $this->request(),
            $this->get(ExpressCheckoutProviderRegistry::class)->get('fixture-express'),
            new BasketViewModel([], Money::fromCents(0), Money::fromCents(0), Money::fromCents(0), 'EUR'),
            new Address(country: 'DE'),
            '',
            PaymentResult::completed(PaymentStatus::PAID)
        );
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
}
