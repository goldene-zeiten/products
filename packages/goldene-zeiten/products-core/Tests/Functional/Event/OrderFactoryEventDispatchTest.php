<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\Order\OrderFactory;
use GoldeneZeiten\Products\EventFixture\MapBillingToDeliveryAddressListener;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;

final class OrderFactoryEventDispatchTest extends AbstractFunctionalTestCase
{
    protected array $testExtensionsToLoad = [
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
