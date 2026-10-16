<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Domain\Dto\Address;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Dto\Checkout\PlacementDetails;
use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\ValueObject\AdjustmentCollection;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\Order\OrderFactory;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Http\ServerRequest;

/**
 * The options of a basket line travel into the order line as a snapshot, so the order still says what was
 * engraved after the option definitions changed.
 */
final class OrderItemLineOptionsTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function theOrderLineKeepsTheOptionsOfTheBasketLine(): void
    {
        $gross = Money::fromCents(10500);
        $option = new LineItemOption('engraving', 'Engraving', 'Anna', 'Anna', Money::fromCents(500), true);
        $item = new BasketViewItem(new Product(), null, 1, $gross, $gross, 0.0, $gross, $gross, Money::fromCents(0), [$option]);
        $basket = new BasketViewModel([$item], $gross, $gross, Money::fromCents(0), 'EUR');

        $order = $this->get(OrderFactory::class)->create(
            new ServerRequest('http://localhost/'),
            $basket,
            new Address(email: 'buyer@example.com', country: 'DE'),
            'invoice',
            new PlacementDetails(new AdjustmentCollection())
        );
        $orderItem = $order->getItems()->toArray()[0];

        $lineOptions = $orderItem->getLineOptions();
        $this->assertCount(1, $lineOptions);
        $this->assertSame('Engraving', $lineOptions[0]->getLabel());
        $this->assertSame('Anna', $lineOptions[0]->getValueLabel());
        $this->assertSame(500, $lineOptions[0]->getSurcharge()->getCents());
        $this->assertSame(WithdrawalExclusion::PERSONALIZED, $orderItem->getWithdrawalExclusion());
    }
}
