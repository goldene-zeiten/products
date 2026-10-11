<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalSettings;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;

/**
 * Works out until when a consumer may withdraw from an order item (Art. 9, 10 and 11a(5) Directive
 * 2011/83/EU). Goods: 14 days after the day the last good of the order was received - a split delivery
 * therefore starts the period only with its last part, and an order that has not been delivered yet
 * has no deadline at all. Digital content: 14 days after the order. Without withdrawal information on
 * a durable medium the period grows by twelve months.
 */
final class WithdrawalPeriodCalculator
{
    private const ORDER_STATUSES_AFTER_SHIPPING = [OrderStatus::SHIPPED, OrderStatus::COMPLETED, OrderStatus::WITHDRAWN];

    /**
     * The last moment a withdrawal for the item can still be submitted, or null while the period has
     * not started to run (goods not delivered yet).
     */
    public function deadlineFor(Order $order, OrderItem $item, WithdrawalSettings $settings): ?\DateTimeImmutable
    {
        $start = $this->periodStart($order, $item, $settings);
        if ($start === null) {
            return null;
        }
        $timezone = $settings->getDateTimeZone();
        $regularEnd = $this->endOfDay($start->setTimezone($timezone)->add($this->days($settings->periodDays)), $timezone);
        $information = $order->getWithdrawalInformationProvidedAt();
        if ($information !== null && \DateTimeImmutable::createFromMutable($information) <= $regularEnd) {
            return $regularEnd;
        }
        $extendedEnd = $this->endOfDay($regularEnd->add(new \DateInterval('P12M')), $timezone);
        if ($information === null) {
            return $extendedEnd;
        }
        $informedEnd = $this->endOfDay(\DateTimeImmutable::createFromMutable($information)->setTimezone($timezone)->add($this->days($settings->periodDays)), $timezone);
        return min($informedEnd, $extendedEnd);
    }

    /**
     * Art. 11a(5): the withdrawal counts as made in time when it was submitted before the period expired.
     */
    public function isTimely(Order $order, OrderItem $item, WithdrawalSettings $settings, \DateTimeImmutable $submittedAt): bool
    {
        if ($settings->periodDays <= 0) {
            return false;
        }
        $deadline = $this->deadlineFor($order, $item, $settings);
        return $deadline === null || $submittedAt <= $deadline;
    }

    private function periodStart(Order $order, OrderItem $item, WithdrawalSettings $settings): ?\DateTimeImmutable
    {
        if ($item->getWithdrawalExclusion() === WithdrawalExclusion::DIGITAL_CONTENT) {
            return $this->immutableOrNull($order->getOrderDate());
        }
        return $this->goodsReceivedAt($order, $settings);
    }

    /**
     * The day the last good reached the consumer, as far as it is known.
     */
    private function goodsReceivedAt(Order $order, WithdrawalSettings $settings): ?\DateTimeImmutable
    {
        $itemDates = [];
        $goodsCount = 0;
        foreach ($order->getItems()->toArray() as $orderItem) {
            if ($orderItem->getWithdrawalExclusion() === WithdrawalExclusion::DIGITAL_CONTENT) {
                continue;
            }
            $goodsCount++;
            if ($orderItem->getDeliveredAt() !== null) {
                $itemDates[] = \DateTimeImmutable::createFromMutable($orderItem->getDeliveredAt());
            }
        }
        $orderDelivery = $this->immutableOrNull($order->getDeliveredAt());
        if ($orderDelivery !== null) {
            return $itemDates === [] ? $orderDelivery : max($orderDelivery, ...$itemDates);
        }
        if ($itemDates !== []) {
            return count($itemDates) === $goodsCount ? max($itemDates) : null;
        }
        return $this->assumedDelivery($order, $settings);
    }

    private function assumedDelivery(Order $order, WithdrawalSettings $settings): ?\DateTimeImmutable
    {
        $shipped = $order->getShippedAt();
        if ($shipped === null && in_array($order->getStatus(), self::ORDER_STATUSES_AFTER_SHIPPING, true)) {
            $shipped = $this->immutableOrNull($order->getOrderDate());
        }
        return $shipped?->add($this->days($settings->assumedDeliveryDays));
    }

    private function days(int $days): \DateInterval
    {
        return new \DateInterval(sprintf('P%dD', max(0, $days)));
    }

    private function endOfDay(\DateTimeImmutable $moment, \DateTimeZone $timezone): \DateTimeImmutable
    {
        return $moment->setTimezone($timezone)->setTime(23, 59, 59);
    }

    private function immutableOrNull(?\DateTime $dateTime): ?\DateTimeImmutable
    {
        return $dateTime === null ? null : \DateTimeImmutable::createFromMutable($dateTime);
    }
}
