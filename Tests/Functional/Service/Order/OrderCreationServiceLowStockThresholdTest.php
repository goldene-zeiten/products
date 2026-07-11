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
use GoldeneZeiten\Products\Tests\Functional\Fixtures\TestMailer;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use Symfony\Component\Mime\Email;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * `stock.lowStockThreshold` is a Site Setting - see ProductsConfigurationFactoryTest for the same
 * class of fix applied to ProductsConfigurationFactory. Stock starts at 10 (see fixture) so a
 * single-unit purchase leaves 9 in stock: above the default threshold (5), but at/below a
 * site-configured threshold of 10 - proving the value actually comes from Site Settings rather
 * than always being the hardcoded default.
 */
final class OrderCreationServiceLowStockThresholdTest extends AbstractFunctionalTestCase
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
        $this->importCSVDataSet(__DIR__ . '/../../Fixtures/order_placement_low_stock_custom_threshold.csv');
        // CreditPointsService still reads Extbase settings eagerly in its constructor (fixed in a
        // later step of this migration), which requires a request resolvable via
        // $GLOBALS['TYPO3_REQUEST'] outside of a real controller dispatch.
        $GLOBALS['TYPO3_REQUEST'] = (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        TestMailer::reset();
    }

    #[Test]
    public function noWarningIsSentWhenOnlyTheDefaultThresholdWouldHaveBeenCrossed(): void
    {
        $this->get(OrderCreationService::class)->create(
            (new ServerRequest('http://localhost/'))->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 0),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertEmpty($this->lowStockEmails());
    }

    #[Test]
    public function aWarningIsSentWhenASiteConfiguredThresholdIsCrossed(): void
    {
        $this->get(OrderCreationService::class)->create(
            $this->requestWithLowStockThreshold(10),
            $this->basketViewModel($this->product()),
            new CheckoutSelections([], 0, 0),
            $this->address(),
            $this->paymentMethod()
        );

        $this->assertNotEmpty($this->lowStockEmails(), 'Expected a low-stock warning email using the site-configured threshold.');
    }

    private function requestWithLowStockThreshold(int $threshold): ServerRequestInterface
    {
        $this->writeSiteConfiguration(
            'products',
            $this->buildSiteConfiguration(2, additionalRootConfiguration: [
                'dependencies' => ['goldene-zeiten/products', 'goldene-zeiten/frontend-test'],
                'settings' => [
                    'products' => [
                        'email' => ['merchantRecipient' => 'merchant@example.com'],
                        'stock' => ['lowStockThreshold' => $threshold],
                    ],
                ],
            ]),
            [$this->buildDefaultLanguageConfiguration('en', '/')]
        );
        $site = $this->get(SiteFinder::class)->getSiteByIdentifier('products');

        return (new ServerRequest('http://localhost/'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE)
            ->withAttribute('site', $site);
    }

    /**
     * @return Email[]
     */
    private function lowStockEmails(): array
    {
        return array_values(array_filter(
            TestMailer::getSentEmails(),
            static fn($email): bool => str_contains((string)$email->getSubject(), 'Low Stock Warning')
        ));
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
        $item = new BasketViewItem($product, null, 1, $unitPriceNet, $unitPriceGross, 0.19, $unitPriceNet, $unitPriceGross, $unitPriceGross->subtract($unitPriceNet));
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
