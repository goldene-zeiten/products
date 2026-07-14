<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\Order\OrderFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Site\Entity\Site;

final class OrderFactoryTest extends AbstractFunctionalTestCase
{
    /**
     * @param non-empty-string $expectedPrefix
     */
    #[Test]
    #[DataProvider('orderNumberPrefixProvider')]
    public function orderNumberUsesCorrectPrefix(?string $sitePrefix, string $expectedPrefix): void
    {
        $request = $sitePrefix !== null
            ? $this->requestWithSite(['order' => ['numberPrefix' => $sitePrefix]])
            : new ServerRequest('http://localhost/');

        $order = $this->subject()->create(
            $request,
            $this->basketViewModel('9.20'),
            $this->address(),
            'invoice',
            $this->placementDetails()
        );

        $this->assertStringStartsWith($expectedPrefix, $order->getOrderNumber());
    }

    /**
     * @return \Generator<string, array<string, ?string>>
     */
    public static function orderNumberPrefixProvider(): \Generator
    {
        yield 'customPrefix' => ['sitePrefix' => 'CUSTOM-', 'expectedPrefix' => 'CUSTOM-'];
        yield 'defaultWithoutSite' => ['sitePrefix' => null, 'expectedPrefix' => 'ORD-'];
    }

    #[Test]
    #[DataProvider('totalGrossRoundingProvider')]
    public function totalGrossRoundingUsesCorrectMode(?string $roundingMode, int $expectedCents): void
    {
        $request = $roundingMode !== null
            ? $this->requestWithSite(['pricing' => ['roundingMode' => $roundingMode]])
            : new ServerRequest('http://localhost/');

        $order = $this->subject()->create(
            $request,
            $this->basketViewModel('9.20'),
            $this->address(),
            'invoice',
            $this->placementDetails()
        );

        $this->assertSame($expectedCents, $order->getTotalGross()->getCents());
    }

    /**
     * @return \Generator<string, array<string, mixed>>
     */
    public static function totalGrossRoundingProvider(): \Generator
    {
        yield 'roundedToNearestInteger' => ['roundingMode' => 'nearestInteger', 'expectedCents' => 900];
        yield 'notRoundedWithoutSite' => ['roundingMode' => null, 'expectedCents' => 920];
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
            new AdjustmentCollection()
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
