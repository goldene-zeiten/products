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
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;

/**
 * `creditPoints.*` are Site Settings - see ProductsConfigurationFactoryTest for the same class of
 * fix applied to ProductsConfigurationFactory.
 */
final class OrderCreationServiceCreditPointsTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'en' => [
            'id' => 0,
            'title' => 'English',
            'locale' => 'en_US.UTF-8',
        ],
    ];

    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/frontend-test',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_placement_with_credit_points.csv');
    }

    #[Test]
    public function identifiedCustomerEarnsAndRedeemsPointsOnPlacement(): void
    {
        $order = $this->get(OrderCreationService::class)->create(
            $this->requestFor(enabled: true, frontendUserUid: 5),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 20),
            $this->address(),
            $this->paymentMethod()
        );

        $rows = $this->ledgerRows($order->getUid() ?? 0);
        $this->assertCount(2, $rows);
        $this->assertContainsEquals(['frontend_user' => 5, 'points' => 20, 'type' => 'earn'], $rows);
        $this->assertContainsEquals(['frontend_user' => 5, 'points' => -20, 'type' => 'redeem'], $rows);
        $this->assertSame(19800, $order->getTotalGross()->getCents());
        $this->assertSame(200, $order->getDiscountTotal()->getCents());
    }

    #[Test]
    public function guestOrdersNeverTouchTheLedgerEvenThoughTheProductEarnsPoints(): void
    {
        $order = $this->get(OrderCreationService::class)->create(
            $this->requestFor(enabled: true, frontendUserUid: 0),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame([], $this->ledgerRows($order->getUid() ?? 0));
    }

    #[Test]
    public function nothingIsRecordedOrDiscountedWhileTheFeatureIsDisabled(): void
    {
        $order = $this->get(OrderCreationService::class)->create(
            $this->requestFor(enabled: false, frontendUserUid: 5),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 20),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertSame([], $this->ledgerRows($order->getUid() ?? 0));
        $this->assertSame(0, $order->getDiscountTotal()->getCents());
    }

    private function requestFor(bool $enabled, int $frontendUserUid): ServerRequestInterface
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'creditPoints' => ['enabled' => $enabled],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $site = $this->get(SiteFinder::class)->getSiteByIdentifier('products');

        $request = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $site);
        if ($frontendUserUid === 0) {
            return $request;
        }
        $frontendUser = new FrontendUserAuthentication();
        $frontendUser->user = ['uid' => $frontendUserUid];
        return $request->withAttribute('frontend.user', $frontendUser);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ledgerRows(int $orderUid): array
    {
        return $this->getConnectionPool()
            ->getConnectionForTable('tx_products_domain_model_creditpointstransaction')
            ->select(['frontend_user', 'points', 'type'], 'tx_products_domain_model_creditpointstransaction', ['order_uid' => $orderUid])
            ->fetchAllAssociative();
    }

    private function product(): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);
        return $product;
    }

    private function basketViewModel(Product $product): BasketViewModel
    {
        $unitPrice = Money::fromDecimalString('100.00');
        $lineTotal = Money::fromDecimalString('200.00');
        $item = new BasketViewItem($product, null, 2, $unitPrice, $unitPrice, 0.0, $lineTotal, $lineTotal, Money::fromCents(0));
        return new BasketViewModel([$item], $lineTotal, $lineTotal, Money::fromCents(0), 'EUR');
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
