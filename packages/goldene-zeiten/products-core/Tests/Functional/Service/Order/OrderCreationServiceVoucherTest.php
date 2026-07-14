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
use GoldeneZeiten\Products\Core\Service\Order\Exception\VoucherRedemptionFailedException;
use GoldeneZeiten\Products\Core\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;

final class OrderCreationServiceVoucherTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/order_placement_with_voucher.csv');
    }

    #[Test]
    public function voucherDiscountReducesTotalGrossButNotNetOrTax(): void
    {
        $subject = $this->get(OrderCreationService::class);

        $order = $subject->create(
            $this->request(),
            $this->basketViewModel($this->product()),
            $this->discountRequest('SAVE10'),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame(1000, $order->getDiscountTotal()->getCents());
        $this->assertSame(['SAVE10'], $order->getVoucherCodes());
        $this->assertSame(9000, $order->getTotalGross()->getCents());
        $this->assertSame(8403, $order->getTotalNet()->getCents());
    }

    #[Test]
    public function appliedVoucherWritesARedemptionRow(): void
    {
        $subject = $this->get(OrderCreationService::class);

        $subject->create(
            $this->request(),
            $this->basketViewModel($this->product()),
            $this->discountRequest('SAVE10'),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/voucher_redemption_save10_added.csv');
    }

    #[Test]
    public function usageLimitIsEnforcedAcrossTwoPlacements(): void
    {
        $subject = $this->get(OrderCreationService::class);
        $subject->create(
            $this->request(),
            $this->basketViewModel($this->product()),
            $this->discountRequest('ONETIME'),
            $this->address(),
            $this->paymentMethod()
        );

        $this->expectException(VoucherRedemptionFailedException::class);
        $this->expectExceptionCode(1783426407);

        $subject->create(
            $this->request(),
            $this->basketViewModel($this->product()),
            $this->discountRequest('ONETIME'),
            $this->address(),
            $this->paymentMethod()
        );
    }

    #[Test]
    public function placementFailsWithoutSideEffectsWhenVoucherIsAlreadyExhausted(): void
    {
        $subject = $this->get(OrderCreationService::class);

        try {
            $subject->create(
                $this->request(),
                $this->basketViewModel($this->product()),
                $this->discountRequest('EXHAUSTED'),
                $this->address(),
                $this->paymentMethod()
            );
            $this->fail('Expected VoucherRedemptionFailedException was not thrown.');
        } catch (VoucherRedemptionFailedException) {
            // expected
        }

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/voucher_exhausted_no_side_effects.csv');
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
}
