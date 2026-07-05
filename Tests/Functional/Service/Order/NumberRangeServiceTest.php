<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Tests\Functional\Service\Order;

use GoldeneZeiten\Products\Service\Order\NumberRangeService;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class NumberRangeServiceTest extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'goldene-zeiten/products',
    ];

    private NumberRangeService $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = $this->get(NumberRangeService::class);
    }

    /**
     * @test
     */
    public function nextStartsAtOneForNewScope(): void
    {
        self::assertSame(1, $this->subject->next('order'));
    }

    /**
     * @test
     */
    public function nextIncrementsSequentiallyForSameScope(): void
    {
        self::assertSame(1, $this->subject->next('order'));
        self::assertSame(2, $this->subject->next('order'));
        self::assertSame(3, $this->subject->next('order'));
    }

    /**
     * @test
     */
    public function nextKeepsIndependentCountersPerScope(): void
    {
        self::assertSame(1, $this->subject->next('order'));
        self::assertSame(2, $this->subject->next('order'));
        self::assertSame(1, $this->subject->next('invoice'));
        self::assertSame(3, $this->subject->next('order'));
        self::assertSame(2, $this->subject->next('invoice'));
    }
}
