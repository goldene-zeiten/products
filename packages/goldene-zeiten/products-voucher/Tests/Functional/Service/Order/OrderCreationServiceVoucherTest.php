<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\Tests\Functional\Service\Order;

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
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use GoldeneZeiten\Products\Voucher\Discount\VoucherCheckoutState;
use GoldeneZeiten\Products\Voucher\Service\Exception\VoucherRedemptionFailedException;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

final class OrderCreationServiceVoucherTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-voucher',
    ];

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
            $this->requestWithVoucher('SAVE10'),
            $this->basketViewModel($this->product()),
            new CheckoutSelections(),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame(1000, $order->getDiscountTotal()->getCents());
        $this->assertSame(9000, $order->getTotalGross()->getCents());
        $this->assertSame(8403, $order->getTotalNet()->getCents());
    }

    #[Test]
    public function appliedVoucherWritesARedemptionRow(): void
    {
        $subject = $this->get(OrderCreationService::class);

        $subject->create(
            $this->requestWithVoucher('SAVE10'),
            $this->basketViewModel($this->product()),
            new CheckoutSelections(),
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
            $this->requestWithVoucher('ONETIME'),
            $this->basketViewModel($this->product()),
            new CheckoutSelections(),
            $this->address(),
            $this->paymentMethod()
        );

        $this->expectException(VoucherRedemptionFailedException::class);
        $this->expectExceptionCode(1783426407);

        $subject->create(
            $this->requestWithVoucher('ONETIME'),
            $this->basketViewModel($this->product()),
            new CheckoutSelections(),
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
                $this->requestWithVoucher('EXHAUSTED'),
                $this->basketViewModel($this->product()),
                new CheckoutSelections(),
                $this->address(),
                $this->paymentMethod()
            );
            $this->fail('Expected VoucherRedemptionFailedException was not thrown.');
        } catch (VoucherRedemptionFailedException) {
            // expected
        }

        $this->assertCSVDataSet(__DIR__ . '/Fixtures/Result/voucher_exhausted_no_side_effects.csv');
    }

    private function requestWithVoucher(string $voucherCode): ServerRequestInterface
    {
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $request = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('frontend.user', $frontendUser);
        $this->get(VoucherCheckoutState::class)->addCode($request, $voucherCode);
        return $request;
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
