<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\Order\Exception\VoucherRedemptionFailedException;
use GoldeneZeiten\Products\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

final class OrderCreationServiceVoucherTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private OrderCreationService $subject;
    private VoucherRedemptionRepository $voucherRedemptionRepository;
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
        $this->voucherRedemptionRepository = $this->get(VoucherRedemptionRepository::class);
        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        $this->product = $product;
    }

    #[Test]
    public function voucherDiscountReducesTotalGrossButNotNetOrTax(): void
    {
        $order = $this->subject->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel(),
            $this->discountRequest('SAVE10'),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame(1000, $order->getDiscountTotal()->getCents());
        self::assertSame(['SAVE10'], $order->getVoucherCodes());
        self::assertSame(9000, $order->getTotalGross()->getCents());
        self::assertSame(8403, $order->getTotalNet()->getCents());
    }

    #[Test]
    public function appliedVoucherWritesARedemptionRow(): void
    {
        $voucher = $this->get(VoucherRepository::class)->findOneByCode('SAVE10');
        self::assertNotNull($voucher);

        $order = $this->subject->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel(),
            $this->discountRequest('SAVE10'),
            $this->address(),
            $this->paymentMethod()
        );

        self::assertSame(1, $this->voucherRedemptionRepository->countFor($voucher));
    }

    #[Test]
    public function usageLimitIsEnforcedAcrossTwoPlacements(): void
    {
        $this->subject->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel(),
            $this->discountRequest('ONETIME'),
            $this->address(),
            $this->paymentMethod()
        );

        $this->expectException(VoucherRedemptionFailedException::class);
        $this->expectExceptionCode(1783426407);

        $this->subject->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel(),
            $this->discountRequest('ONETIME'),
            $this->address(),
            $this->paymentMethod()
        );
    }

    #[Test]
    public function placementFailsWithoutSideEffectsWhenVoucherIsAlreadyExhausted(): void
    {
        $orderCountBefore = $this->countOrders();
        $stockBefore = $this->currentStock();

        try {
            $this->subject->create(
                new ServerRequest('http://localhost/'),
                $this->basketViewModel(),
                $this->discountRequest('EXHAUSTED'),
                $this->address(),
                $this->paymentMethod()
            );
            self::fail('Expected VoucherRedemptionFailedException was not thrown.');
        } catch (VoucherRedemptionFailedException) {
            // expected
        }

        self::assertSame($orderCountBefore, $this->countOrders());
        self::assertSame($stockBefore, $this->currentStock());
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

    private function discountRequest(string $voucherCode): CheckoutSelections
    {
        return new CheckoutSelections([$voucherCode], 0);
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
