<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewItem;
use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Model\Product;
use GoldeneZeiten\Products\Core\Domain\Repository\ProductRepository;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Express\ExpressBasketFactory;
use GoldeneZeiten\Products\Core\Express\ExpressBasketTokenService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The factory snapshots the live basket into the token payload every express button issues. What matters is
 * that the snapshot carries the shipping-relevant facts (item quantities, the goods total, the currency and
 * the customer) and that it survives the sign/verify round-trip unchanged - so the shipping-rate callback
 * quotes the very basket the button was rendered for.
 */
final class ExpressBasketFactoryTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/../Service/Order/Fixtures/order_placement.csv');
    }

    #[Test]
    public function theSnapshotCarriesTheBasketAndSurvivesTheTokenRoundTrip(): void
    {
        $expressBasket = $this->get(ExpressBasketFactory::class)->createFromBasket($this->basketViewModel(), 7);

        $this->assertSame('EUR', $expressBasket->getCurrency());
        $this->assertSame(20000, $expressBasket->getTotalGross()->getCents());

        $payload = $expressBasket->toArray();
        $this->assertCount(1, $payload['items']);
        $this->assertSame(2, $payload['items'][0]['quantity']);
        $this->assertSame(7, $payload['frontendUserUid']);

        $tokenService = $this->get(ExpressBasketTokenService::class);
        $resolved = $tokenService->resolve($tokenService->issue($expressBasket));

        $this->assertNotNull($resolved);
        $this->assertSame($payload, $resolved->toArray());
    }

    private function basketViewModel(): BasketViewModel
    {
        $rowNet = Money::fromDecimalString('168.06');
        $rowGross = Money::fromDecimalString('200.00');
        $item = new BasketViewItem(
            $this->product(),
            null,
            2,
            Money::fromDecimalString('84.03'),
            Money::fromDecimalString('100.00'),
            0.19,
            $rowNet,
            $rowGross,
            $rowGross->subtract($rowNet)
        );

        return new BasketViewModel([$item], $rowNet, $rowGross, $rowGross->subtract($rowNet), 'EUR');
    }

    private function product(): Product
    {
        $product = $this->get(ProductRepository::class)->findByUid(1);
        $this->assertInstanceOf(Product::class, $product);

        return $product;
    }
}
