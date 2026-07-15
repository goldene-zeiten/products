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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * The free-shipping voucher spans two features the core keeps apart: the carrier charge that shipping
 * produces, and the discount the voucher add-on produces to offset it. This proves the add-on negates the
 * carrier's cost without hiding the shipping line - so the order still records what shipping cost and who
 * waived it - and that a voucher that does not waive shipping leaves the cost standing. It exercises the
 * core shipping step, so it guards the interaction that survives the coming shipping extraction.
 */
final class FreeShippingVoucherTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products-voucher',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/FreeShippingVoucherTest/order_placement_with_shipping.csv');
    }

    /**
     * @param string[] $voucherCodes
     */
    #[Test]
    #[DataProvider('freeShippingScenarioProvider')]
    public function aFreeShippingVoucherOffsetsTheCarrierCostWithoutHidingTheShippingLine(array $voucherCodes, int $expectedTotalGrossCents): void
    {
        $order = $this->get(OrderCreationService::class)->create(
            $this->requestWith($voucherCodes),
            $this->basketViewModel($this->product()),
            new CheckoutSelections('tablerate:1'),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame('tablerate', $order->getShippingProvider());
        $this->assertSame('1', $order->getShippingOption());
        // The shipping line stays visible at the carrier's rate whatever the voucher does.
        $this->assertSame(500, $order->getShippingTotal()->getCents());
        $this->assertSame($expectedTotalGrossCents, $order->getTotalGross()->getCents());
    }

    /**
     * @return \Generator<string, array{voucherCodes: string[], expectedTotalGrossCents: int}>
     */
    public static function freeShippingScenarioProvider(): \Generator
    {
        // 100.00 goods + 5.00 carrier shipping.
        yield 'no voucher pays the full shipping' => ['voucherCodes' => [], 'expectedTotalGrossCents' => 10500];
        yield 'free-shipping voucher offsets the carrier cost' => ['voucherCodes' => ['FREESHIP'], 'expectedTotalGrossCents' => 10000];
        yield 'a voucher that does not waive shipping leaves the cost standing' => ['voucherCodes' => ['REGULAR'], 'expectedTotalGrossCents' => 10500];
    }

    /**
     * @param string[] $voucherCodes
     */
    private function requestWith(array $voucherCodes): ServerRequestInterface
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['shipping' => ['enabled' => true]]]]);
        $frontendUser = GeneralUtility::makeInstance(FrontendUserAuthentication::class);
        $frontendUser->initializeUserSessionManager();
        $request = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $site)
            ->withAttribute('frontend.user', $frontendUser);
        $voucherCheckoutState = $this->get(VoucherCheckoutState::class);
        foreach ($voucherCodes as $voucherCode) {
            $voucherCheckoutState->addCode($request, $voucherCode);
        }

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
