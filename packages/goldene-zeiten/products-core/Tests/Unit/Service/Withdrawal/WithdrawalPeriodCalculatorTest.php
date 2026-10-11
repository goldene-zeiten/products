<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Tests\Unit\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalSettings;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Service\Withdrawal\WithdrawalPeriodCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

final class WithdrawalPeriodCalculatorTest extends UnitTestCase
{
    #[Test]
    public function goodsPeriodEndsFourteenDaysAfterTheDayOfDelivery(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = $this->goodsItem();
        $order = $this->order([$item], OrderStatus::SHIPPED);
        $order->setDeliveredAt(new \DateTime('2026-03-10 15:30:00', new \DateTimeZone('UTC')));

        $deadline = $subject->deadlineFor($order, $item, $this->settings());

        $this->assertSame('2026-03-24 23:59:59', $deadline?->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function aSplitDeliveryStartsThePeriodAtTheLastGoodReceived(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $first = $this->goodsItem();
        $first->setDeliveredAt(new \DateTime('2026-03-02', new \DateTimeZone('UTC')));
        $second = $this->goodsItem();
        $second->setDeliveredAt(new \DateTime('2026-03-09', new \DateTimeZone('UTC')));
        $order = $this->order([$first, $second], OrderStatus::SHIPPED);

        $deadline = $subject->deadlineFor($order, $first, $this->settings());

        $this->assertSame('2026-03-23', $deadline?->format('Y-m-d'));
    }

    #[Test]
    public function thePeriodHasNotStartedWhileOneGoodOfASplitDeliveryIsStillOutstanding(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $first = $this->goodsItem();
        $first->setDeliveredAt(new \DateTime('2026-03-02', new \DateTimeZone('UTC')));
        $second = $this->goodsItem();
        $order = $this->order([$first, $second], OrderStatus::SHIPPED);

        $this->assertNull($subject->deadlineFor($order, $first, $this->settings()));
    }

    #[Test]
    public function aLaterOrderLevelDeliveryDateOverridesEarlierItemDeliveries(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = $this->goodsItem();
        $item->setDeliveredAt(new \DateTime('2026-03-02', new \DateTimeZone('UTC')));
        $order = $this->order([$item], OrderStatus::COMPLETED);
        $order->setDeliveredAt(new \DateTime('2026-03-12', new \DateTimeZone('UTC')));

        $deadline = $subject->deadlineFor($order, $item, $this->settings());

        $this->assertSame('2026-03-26', $deadline?->format('Y-m-d'));
    }

    #[Test]
    public function aShippedOrderWithoutDeliveryDateUsesTheShippingDatePlusTheAssumedDeliveryDays(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = $this->goodsItem();
        $order = $this->order([$item], OrderStatus::SHIPPED);
        $order->setStatusLog([['from' => 'confirmed', 'to' => 'shipped', 'at' => '2026-03-01T10:00:00+00:00']]);

        $deadline = $subject->deadlineFor($order, $item, $this->settings(assumedDeliveryDays: 3));

        $this->assertSame('2026-03-18', $deadline?->format('Y-m-d'));
    }

    #[Test]
    #[DataProvider('unstartedStatusProvider')]
    public function theWithdrawalStaysOpenBeforeTheGoodsLeftTheWarehouse(OrderStatus $status): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = $this->goodsItem();
        $order = $this->order([$item], $status);

        $this->assertNull($subject->deadlineFor($order, $item, $this->settings()));
        $this->assertTrue($subject->isTimely($order, $item, $this->settings(), new \DateTimeImmutable('2030-01-01')));
    }

    public static function unstartedStatusProvider(): \Generator
    {
        yield 'new' => ['status' => OrderStatus::NEW];
        yield 'confirmed' => ['status' => OrderStatus::CONFIRMED];
    }

    #[Test]
    public function digitalContentRunsFromTheConclusionOfTheContract(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = new OrderItem();
        $item->setWithdrawalExclusion(WithdrawalExclusion::DIGITAL_CONTENT);
        $order = $this->order([$item], OrderStatus::COMPLETED);
        $order->setDeliveredAt(new \DateTime('2026-06-01', new \DateTimeZone('UTC')));

        $deadline = $subject->deadlineFor($order, $item, $this->settings());

        $this->assertSame('2026-03-15', $deadline?->format('Y-m-d'));
    }

    #[Test]
    public function withoutWithdrawalInformationThePeriodIsExtendedByTwelveMonths(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = $this->goodsItem();
        $order = $this->order([$item], OrderStatus::COMPLETED, informed: false);
        $order->setDeliveredAt(new \DateTime('2026-03-10', new \DateTimeZone('UTC')));

        $deadline = $subject->deadlineFor($order, $item, $this->settings());

        $this->assertSame('2027-03-24', $deadline?->format('Y-m-d'));
    }

    #[Test]
    public function informationGivenAfterThePeriodRestartsItFromTheDayOfInformation(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = $this->goodsItem();
        $order = $this->order([$item], OrderStatus::COMPLETED, informed: false);
        $order->setDeliveredAt(new \DateTime('2026-03-10', new \DateTimeZone('UTC')));
        $order->setWithdrawalInformationProvidedAt(new \DateTime('2026-05-01', new \DateTimeZone('UTC')));

        $deadline = $subject->deadlineFor($order, $item, $this->settings());

        $this->assertSame('2026-05-15', $deadline?->format('Y-m-d'));
    }

    #[Test]
    #[DataProvider('timelinessProvider')]
    public function aWithdrawalIsTimelyWhenItIsSubmittedBeforeThePeriodExpires(string $submittedAt, bool $expected): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = $this->goodsItem();
        $order = $this->order([$item], OrderStatus::COMPLETED);
        $order->setDeliveredAt(new \DateTime('2026-03-10', new \DateTimeZone('UTC')));

        $result = $subject->isTimely($order, $item, $this->settings(), new \DateTimeImmutable($submittedAt, new \DateTimeZone('UTC')));

        $this->assertSame($expected, $result);
    }

    public static function timelinessProvider(): \Generator
    {
        yield 'on the last day, late in the evening' => ['submittedAt' => '2026-03-24 23:30:00', 'expected' => true];
        yield 'the first second after the last day' => ['submittedAt' => '2026-03-25 00:00:00', 'expected' => false];
        yield 'long after' => ['submittedAt' => '2026-09-01 12:00:00', 'expected' => false];
    }

    #[Test]
    public function aZeroDayPeriodDisablesTheWithdrawal(): void
    {
        $subject = new WithdrawalPeriodCalculator();
        $item = $this->goodsItem();
        $order = $this->order([$item], OrderStatus::CONFIRMED);

        $this->assertFalse($subject->isTimely($order, $item, $this->settings(periodDays: 0), new \DateTimeImmutable('2026-03-02')));
    }

    private function settings(int $periodDays = 14, int $assumedDeliveryDays = 14): WithdrawalSettings
    {
        return new WithdrawalSettings(periodDays: $periodDays, assumedDeliveryDays: $assumedDeliveryDays, timezone: 'UTC');
    }

    private function goodsItem(): OrderItem
    {
        return new OrderItem();
    }

    /**
     * @param OrderItem[] $items
     */
    private function order(array $items, OrderStatus $status, bool $informed = true): Order
    {
        $order = new Order();
        $order->setOrderDate(new \DateTime('2026-03-01 09:00:00', new \DateTimeZone('UTC')));
        $order->setStatus($status);
        if ($informed) {
            $order->setWithdrawalInformationProvidedAt(new \DateTime('2026-03-01 09:00:00', new \DateTimeZone('UTC')));
        }
        foreach ($items as $item) {
            $order->getItems()->attach($item);
        }
        return $order;
    }
}
