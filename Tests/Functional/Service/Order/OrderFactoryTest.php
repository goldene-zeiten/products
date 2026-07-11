<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Domain\Dto\Checkout\ShippingSelection;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Service\Order\OrderFactory;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * `order.numberPrefix`/`pricing.roundingMode` are Site Settings - see
 * ProductsConfigurationFactoryTest for the same class of fix applied to
 * ProductsConfigurationFactory.
 */
final class OrderFactoryTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    #[Test]
    public function orderNumberUsesThePrefixConfiguredInSiteSettings(): void
    {
        $order = $this->subject()->create(
            $this->requestWithSite(['order' => ['numberPrefix' => 'CUSTOM-']]),
            $this->basketViewModel('9.20'),
            $this->address(),
            'invoice',
            $this->placementDetails()
        );

        self::assertStringStartsWith('CUSTOM-', $order->getOrderNumber());
    }

    #[Test]
    public function orderNumberDefaultsToOrdWithoutASite(): void
    {
        $order = $this->subject()->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel('9.20'),
            $this->address(),
            'invoice',
            $this->placementDetails()
        );

        self::assertStringStartsWith('ORD-', $order->getOrderNumber());
    }

    #[Test]
    public function totalGrossIsRoundedUsingTheSiteConfiguredRoundingMode(): void
    {
        $order = $this->subject()->create(
            $this->requestWithSite(['pricing' => ['roundingMode' => 'nearestInteger']]),
            $this->basketViewModel('9.20'),
            $this->address(),
            'invoice',
            $this->placementDetails()
        );

        self::assertSame(900, $order->getTotalGross()->getCents());
    }

    #[Test]
    public function totalGrossIsNotRoundedWithoutASite(): void
    {
        $order = $this->subject()->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel('9.20'),
            $this->address(),
            'invoice',
            $this->placementDetails()
        );

        self::assertSame(920, $order->getTotalGross()->getCents());
    }

    private function subject(): OrderFactory
    {
        return $this->get(OrderFactory::class);
    }

    /**
     * @param array<string, mixed> $productsSettings
     */
    private function requestWithSite(array $productsSettings): ServerRequestInterface
    {
        $site = new Site('products', 1, ['settings' => ['products' => $productsSettings]]);
        return (new ServerRequest('http://localhost/'))->withAttribute('site', $site);
    }

    private function placementDetails(): PlacementDetails
    {
        return new PlacementDetails(
            new BasketDiscountSummary([], Money::fromCents(0)),
            Money::fromCents(0),
            ShippingSelection::none(),
            Money::fromCents(0)
        );
    }

    private function basketViewModel(string $unitPriceGross): BasketViewModel
    {
        $gross = Money::fromDecimalString($unitPriceGross);
        $item = new BasketViewItem(new Product(), null, 1, $gross, $gross, 0.0, $gross, $gross, Money::fromCents(0));
        return new BasketViewModel([$item], $gross, $gross, Money::fromCents(0), 'EUR');
    }

    private function address(): Address
    {
        return new Address(email: 'buyer@example.com', country: 'DE');
    }
}
