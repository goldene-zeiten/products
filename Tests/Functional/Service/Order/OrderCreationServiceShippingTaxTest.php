<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Configuration\ProductsConfigurationFactory;
use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\CheckoutSelections;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\Repository\CreditPointsTransactionRepository;
use GoldeneZeiten\Products\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Domain\Repository\VoucherRedemptionRepository;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Payment\PaymentMethodInterface;
use GoldeneZeiten\Products\Payment\PaymentMethodRegistry;
use GoldeneZeiten\Products\Service\CreditPoints\CreditPointsService;
use GoldeneZeiten\Products\Service\FrontendUserResolver;
use GoldeneZeiten\Products\Service\Order\OrderCreationService;
use GoldeneZeiten\Products\Service\Order\OrderFactory;
use GoldeneZeiten\Products\Service\Order\StockService;
use GoldeneZeiten\Products\Service\Shipping\HandlingFeeService;
use GoldeneZeiten\Products\Service\Shipping\ShippingCostService;
use GoldeneZeiten\Products\Service\Voucher\VoucherService;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * Covers the phase 7 shipping-tax split (OrderFactory::applyAdjustments() reverse-splitting the
 * shipping method's gross rate/tax-rate-override into totalNet/totalTax) and the FE-usergroup
 * discount applied to the shipping rate, through a direct OrderCreationService::create() call
 * rather than a full HTTP checkout flow - TaxService/ShippingCostService/HandlingFeeService are
 * stateless (take an explicit ProductsConfiguration), so no request/site-config faking is needed
 * to exercise this behaviour.
 */
final class OrderCreationServiceShippingTaxTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_shipping_tax_and_discount.csv');
        // OrderFactory reads Extbase settings eagerly in its constructor, which requires a request
        // to be resolvable via $GLOBALS['TYPO3_REQUEST'] outside of a real controller dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $product = $this->get(ProductRepository::class)->findByUid(1);
        self::assertInstanceOf(Product::class, $product);
        $this->product = $product;
    }

    #[Test]
    public function shippingCostUsesTheStandardTaxClassRateByDefault(): void
    {
        $order = $this->subject()->create(
            $this->requestFor(0),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        // 5.00 gross shipping at 19% standard rate: net = round(500 / 1.19) = 420, tax = 80.
        self::assertSame(500, $order->getShippingTotal()->getCents());
        self::assertSame(8403 + 420, $order->getTotalNet()->getCents());
        self::assertSame(1597 + 80, $order->getTotalTax()->getCents());
    }

    #[Test]
    public function shippingCostUsesTheMethodsTaxRateOverrideWhenEnabled(): void
    {
        $order = $this->subject()->create(
            $this->requestFor(0),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 2),
            $this->address(),
            $this->paymentMethod()
        );

        // 5.00 gross shipping at the method's 7% override: net = round(500 / 1.07) = 467, tax = 33.
        self::assertSame(500, $order->getShippingTotal()->getCents());
        self::assertSame(8403 + 467, $order->getTotalNet()->getCents());
        self::assertSame(1597 + 33, $order->getTotalTax()->getCents());
    }

    #[Test]
    public function shippingCostReflectsTheShoppersFeUsergroupDiscount(): void
    {
        // user 1 belongs to group 1, which carries a 15% discount (see fixture).
        $order = $this->subject()->create(
            $this->requestFor(1),
            $this->basketViewModel(),
            new CheckoutSelections([], 0, 1),
            $this->address(),
            $this->paymentMethod()
        );

        // 5.00 * 0.85 = 4.25 gross shipping.
        self::assertSame(425, $order->getShippingTotal()->getCents());
    }

    private function subject(): OrderCreationService
    {
        return new OrderCreationService(
            $this->get(StockService::class),
            $this->get(OrderRepository::class),
            $this->get(OrderFactory::class),
            $this->get(PersistenceManagerInterface::class),
            $this->get(EventDispatcherInterface::class),
            $this->get(VoucherService::class),
            $this->get(VoucherRedemptionRepository::class),
            $this->get(CreditPointsService::class),
            $this->get(CreditPointsTransactionRepository::class),
            $this->get(FrontendUserResolver::class),
            $this->get(ShippingCostService::class),
            $this->get(HandlingFeeService::class),
            $this->get(ConfigurationManagerInterface::class),
            new ProductsConfigurationFactory($this->fakeConfigurationManager())
        );
    }

    private function fakeConfigurationManager(): ConfigurationManagerInterface
    {
        return new class () implements ConfigurationManagerInterface {
            /**
             * @return array<string, mixed>
             */
            public function getConfiguration(string $configurationType, ?string $extensionName = null, ?string $pluginName = null): array
            {
                return ['shipping' => ['enabled' => true]];
            }

            /**
             * @param array<string, mixed> $configuration
             */
            public function setConfiguration(array $configuration = []): void {}

            public function setRequest(ServerRequestInterface $request): void {}
        };
    }

    private function requestFor(int $frontendUserUid): ServerRequestInterface
    {
        $request = new ServerRequest('http://localhost/');
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
