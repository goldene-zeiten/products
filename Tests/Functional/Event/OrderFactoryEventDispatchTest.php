<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Event;

use GoldeneZeiten\Products\Domain\Dto\Address;
use GoldeneZeiten\Products\Domain\Dto\BasketDiscountSummary;
use GoldeneZeiten\Products\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Domain\Dto\Checkout\ShippingSelection;
use GoldeneZeiten\Products\Domain\Model\Product;
use GoldeneZeiten\Products\Domain\ValueObject\Money;
use GoldeneZeiten\Products\EventFixture\MapBillingToDeliveryAddressListener;
use GoldeneZeiten\Products\Service\Order\OrderFactory;
use GoldeneZeiten\Products\Tests\Functional\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;

final class OrderFactoryEventDispatchTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
        'goldene-zeiten/products-event-fixture',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        MapBillingToDeliveryAddressListener::$enabled = false;
        MapBillingToDeliveryAddressListener::$invocationCount = 0;
    }

    #[Test]
    public function mapBillingToDeliveryAddressEventIsDispatchedAndMutationTakesEffect(): void
    {
        MapBillingToDeliveryAddressListener::$enabled = true;

        $order = $this->subject()->create(
            new ServerRequest('http://localhost/'),
            $this->basketViewModel('9.20'),
            $this->address(),
            'invoice',
            $this->placementDetails()
        );

        $this->assertGreaterThanOrEqual(1, MapBillingToDeliveryAddressListener::$invocationCount);
        $this->assertSame('EVENT-DELIVERY', $order->getDeliveryAddress()?->getFirstName());
    }

    private function subject(): OrderFactory
    {
        return $this->get(OrderFactory::class);
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
