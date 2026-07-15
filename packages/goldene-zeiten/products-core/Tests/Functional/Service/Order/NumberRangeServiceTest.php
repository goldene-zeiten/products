<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Core\Service\Order\NumberRangeService;
use GoldeneZeiten\Products\Testing\AbstractFunctionalTestCase;
use PHPUnit\Framework\Attributes\Test;

final class NumberRangeServiceTest extends AbstractFunctionalTestCase
{
    #[Test]
    public function nextStartsAtOneForNewScope(): void
    {
        $subject = $this->get(NumberRangeService::class);

        $this->assertSame(1, $subject->next('order'));
    }

    #[Test]
    public function nextIncrementsSequentiallyForSameScope(): void
    {
        $subject = $this->get(NumberRangeService::class);

        $this->assertSame(1, $subject->next('order'));
        $this->assertSame(2, $subject->next('order'));
        $this->assertSame(3, $subject->next('order'));
    }

    #[Test]
    public function nextKeepsIndependentCountersPerScope(): void
    {
        $subject = $this->get(NumberRangeService::class);

        $this->assertSame(1, $subject->next('order'));
        $this->assertSame(2, $subject->next('order'));
        $this->assertSame(1, $subject->next('invoice'));
        $this->assertSame(3, $subject->next('order'));
        $this->assertSame(2, $subject->next('invoice'));
    }
}
