<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Unit\Domain\Dto;

use GoldeneZeiten\Products\Domain\Dto\Basket;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class BasketTest extends UnitTestCase
{
    #[Test]
    public function voucherCodesStartEmpty(): void
    {
        $basket = new Basket();

        self::assertSame([], $basket->getVoucherCodes());
    }

    #[Test]
    public function addingAVoucherCodeIsIdempotent(): void
    {
        $basket = new Basket();

        $basket->addVoucherCode('SAVE10');
        $basket->addVoucherCode('SAVE10');

        self::assertSame(['SAVE10'], $basket->getVoucherCodes());
    }

    #[Test]
    public function multipleDistinctCodesCanBeApplied(): void
    {
        $basket = new Basket();

        $basket->addVoucherCode('SAVE10');
        $basket->addVoucherCode('FLAT5');

        self::assertSame(['SAVE10', 'FLAT5'], $basket->getVoucherCodes());
    }

    #[Test]
    public function removingAVoucherCodeLeavesOthersIntact(): void
    {
        $basket = new Basket();
        $basket->addVoucherCode('SAVE10');
        $basket->addVoucherCode('FLAT5');

        $basket->removeVoucherCode('SAVE10');

        self::assertSame(['FLAT5'], $basket->getVoucherCodes());
    }

    #[Test]
    public function clearVoucherCodesRemovesEverything(): void
    {
        $basket = new Basket();
        $basket->addVoucherCode('SAVE10');
        $basket->addVoucherCode('FLAT5');

        $basket->clearVoucherCodes();

        self::assertSame([], $basket->getVoucherCodes());
    }

    #[Test]
    public function constructorAcceptsInitialVoucherCodes(): void
    {
        $basket = new Basket([], ['SAVE10', 'FLAT5']);

        self::assertSame(['SAVE10', 'FLAT5'], $basket->getVoucherCodes());
    }
}
