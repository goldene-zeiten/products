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

    #[Test]
    public function voucherCodesStartEmpty(): void
    {
        $basket = new Basket();

        $this->assertSame([], $basket->getVoucherCodes());
    }

    #[Test]
    public function addingAVoucherCodeIsIdempotent(): void
    {
        $basket = new Basket();

        $basket->addVoucherCode('SAVE10');
        $basket->addVoucherCode('SAVE10');

        $this->assertSame(['SAVE10'], $basket->getVoucherCodes());
    }

    #[Test]
    public function multipleDistinctCodesCanBeApplied(): void
    {
        $basket = new Basket();

        $basket->addVoucherCode('SAVE10');
        $basket->addVoucherCode('FLAT5');

        $this->assertSame(['SAVE10', 'FLAT5'], $basket->getVoucherCodes());
    }

    #[Test]
    public function removingAVoucherCodeLeavesOthersIntact(): void
    {
        $basket = new Basket();
        $basket->addVoucherCode('SAVE10');
        $basket->addVoucherCode('FLAT5');

        $basket->removeVoucherCode('SAVE10');

        $this->assertSame(['FLAT5'], $basket->getVoucherCodes());
    }

    #[Test]
    public function clearVoucherCodesRemovesEverything(): void
    {
        $basket = new Basket();
        $basket->addVoucherCode('SAVE10');
        $basket->addVoucherCode('FLAT5');

        $basket->clearVoucherCodes();

        $this->assertSame([], $basket->getVoucherCodes());
    }

    #[Test]
    public function constructorAcceptsInitialVoucherCodes(): void
    {
        $basket = new Basket([], ['SAVE10', 'FLAT5']);

        $this->assertSame(['SAVE10', 'FLAT5'], $basket->getVoucherCodes());
    }
}
