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
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Covers shipping-tax split via {@see OrderFactory::applyAdjustments()} and FE-usergroup discounts.
 */
final class OrderCreationServiceShippingTaxTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Fixtures/OrderCreationServiceShippingTaxTest/order_shipping_tax_and_discount.csv');
    }

    #[Test]
    #[DataProvider('shippingCostTaxCalculationProvider')]
    public function shippingCostUsesTaxRateCorrectly(int $methodId, int $expectedNetAddition, int $expectedTaxAddition): void
    {
        $subject = $this->subject();

        $order = $subject->create(
            $this->requestFor(0),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 'tablerate:' . $methodId),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame(500, $order->getShippingTotal()->getCents());
        $this->assertSame(8403 + $expectedNetAddition, $order->getTotalNet()->getCents());
        $this->assertSame(1597 + $expectedTaxAddition, $order->getTotalTax()->getCents());
    }

    /**
     * @return \Generator<string, array<string, int>>
     */
    public static function shippingCostTaxCalculationProvider(): \Generator
    {
        // 5.00 gross shipping at 19% standard rate: net = round(500 / 1.19) = 420, tax = 80.
        yield 'standardTaxRate' => ['methodId' => 1, 'expectedNetAddition' => 420, 'expectedTaxAddition' => 80];
        // 5.00 gross shipping at the method's 7% override: net = round(500 / 1.07) = 467, tax = 33.
        yield 'methodTaxRateOverride' => ['methodId' => 2, 'expectedNetAddition' => 467, 'expectedTaxAddition' => 33];
    }

    #[Test]
    public function shippingCostReflectsTheShoppersFeUsergroupDiscount(): void
    {
        $subject = $this->subject();

        // user 1 belongs to group 1, which carries a 15% discount (see fixture).
        $order = $subject->create(
            $this->requestFor(1),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 'tablerate:1'),
            $this->address(),
            $this->paymentMethod()
        );

        // 5.00 * 0.85 = 4.25 gross shipping.
        $this->assertSame(425, $order->getShippingTotal()->getCents());
    }

    private function subject(): OrderCreationService
    {
        return $this->get(OrderCreationService::class);
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $site = new Site('products', 1, ['settings' => ['products' => ['shipping' => ['enabled' => true]]]]);
        $request = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $site);
        if ($frontendUserUid === 0) {
            return $request;
        }
        $queryBuilder = $this->getConnectionPool()->getQueryBuilderForTable('fe_users');
        $row = $queryBuilder->select('*')
            ->from('fe_users')
            ->where($queryBuilder->expr()->eq('uid', $frontendUserUid))
            ->executeQuery()
            ->fetchAssociative();
        $frontendUser = new FrontendUserAuthentication();
        $frontendUser->user = $row !== false ? $row : ['uid' => $frontendUserUid];
        return $request->withAttribute('frontend.user', $frontendUser);
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
