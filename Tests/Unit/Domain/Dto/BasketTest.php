<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Domain\Dto;

use GoldeneZeiten\Products\Domain\Dto\Basket;
use GoldeneZeiten\Products\Domain\Dto\BasketItem;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BasketTest extends UnitTestCase
{
    #[Test]
    public function addItemFloorsANegativeQuantityAtOne(): void
    {
        $basket = new Basket();

        $basket->addItem(new BasketItem(1, null, -5));

        $this->assertSame(1, $basket->getItems()[0]->getQuantity());
    }

    #[Test]
    public function addItemWithNegativeQuantityCannotReduceAnExistingLine(): void
    {
        $basket = new Basket();
        $basket->addItem(new BasketItem(1, null, 3));

        $basket->addItem(new BasketItem(1, null, -5));

        $this->assertSame(4, $basket->getItems()[0]->getQuantity());
    }
}
