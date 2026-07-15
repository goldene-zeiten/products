<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Service;

use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Service\PriceRoundingService;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class PriceRoundingServiceTest extends UnitTestCase
{
    private PriceRoundingService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new PriceRoundingService();
    }

    #[Test]
    public function noneModeLeavesTheAmountUnchanged(): void
    {
        $result = $this->subject->round(Money::fromDecimalString('23.45'), PriceRoundingService::MODE_NONE);

        $this->assertSame(2345, $result->getCents());
    }

    #[Test]
    public function anUnknownModeIsTreatedAsNone(): void
    {
        $result = $this->subject->round(Money::fromDecimalString('23.45'), 'bogus');

        $this->assertSame(2345, $result->getCents());
    }

    #[Test]
    public function nearestIntegerRoundsHalfAwayFromZero(): void
    {
        $this->assertSame(2300, $this->subject->round(Money::fromDecimalString('23.45'), PriceRoundingService::MODE_NEAREST_INTEGER)->getCents());
        $this->assertSame(2400, $this->subject->round(Money::fromDecimalString('23.55'), PriceRoundingService::MODE_NEAREST_INTEGER)->getCents());
    }

    #[Test]
    public function psychological99RoundsAWholeAmountDownToThePreviousCharmPrice(): void
    {
        $result = $this->subject->round(Money::fromDecimalString('20.00'), PriceRoundingService::MODE_PSYCHOLOGICAL_99);

        $this->assertSame(1999, $result->getCents());
    }

    #[Test]
    public function psychological99RoundsAFractionalAmountUpToTheNextCharmPrice(): void
    {
        $result = $this->subject->round(Money::fromDecimalString('23.45'), PriceRoundingService::MODE_PSYCHOLOGICAL_99);

        $this->assertSame(2399, $result->getCents());
    }

    #[Test]
    public function psychological99LeavesAnAlreadyCharmPricedAmountUnchanged(): void
    {
        $result = $this->subject->round(Money::fromDecimalString('23.99'), PriceRoundingService::MODE_PSYCHOLOGICAL_99);

        $this->assertSame(2399, $result->getCents());
    }

    #[Test]
    public function psychological99NeverGoesNegative(): void
    {
        $result = $this->subject->round(Money::fromCents(0), PriceRoundingService::MODE_PSYCHOLOGICAL_99);

        $this->assertSame(0, $result->getCents());
    }
}
