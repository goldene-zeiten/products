<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalItemState;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalSettings;
use GoldeneZeiten\Products\Core\Domain\Dto\Withdrawal\WithdrawalStatement;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalItemAvailability;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;
use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager;
use GoldeneZeiten\Products\Core\Service\Order\StockService;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalRequestException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\InvalidWithdrawalTokenException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\OrderNotWithdrawableException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalEmailMismatchException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalNameMissingException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalNoItemSelectedException;
use GoldeneZeiten\Products\Core\Service\Withdrawal\Exception\WithdrawalPeriodExpiredException;
use TYPO3\CMS\Extbase\Persistence\PersistenceManagerInterface;

/**
 * Customer self-service withdrawal from a distance contract (Art. 9 to 11a Directive 2011/83/EU),
 * whole or in part. Items that have not left the warehouse go back into stock and the order is
 * cancelled once nothing remains; for shipped orders the declaration is recorded and the order waits
 * in the withdrawn status until the backend marks the goods returned.
 */
final class WithdrawalService
{
    private const STATUSES_AFTER_SHIPPING = [OrderStatus::SHIPPED, OrderStatus::COMPLETED, OrderStatus::WITHDRAWN];

    public function __construct(
        private readonly OrderRepository $orderRepository,
        private readonly WithdrawalTokenService $withdrawalTokenService,
        private readonly OrderStatusManager $orderStatusManager,
        private readonly OrderMailService $orderMailService,
        private readonly PersistenceManagerInterface $persistenceManager,
        private readonly WithdrawalPeriodCalculator $periodCalculator,
        private readonly StockService $stockService
    ) {}

    /**
     * @throws InvalidWithdrawalTokenException
     */
    public function resolveOrder(int $orderUid, string $hash): Order
    {
        $order = $this->orderRepository->findByUidIgnoringStoragePage($orderUid);
        if ($order === null || !$this->withdrawalTokenService->isValid($order, $hash)) {
            throw new InvalidWithdrawalTokenException(
                sprintf('Invalid or expired withdrawal token for order %d.', $orderUid),
                1752100000
            );
        }
        return $order;
    }

    /**
     * The plain form's counterpart to {@see resolveOrder()}: no capability token, so both lookup
     * failure modes collapse into one generic exception - the response must not reveal whether an
     * order number exists. Callers rate-limit this path.
     *
     * @throws InvalidWithdrawalRequestException
     */
    public function resolveOrderByNumberAndEmail(string $orderNumber, string $email): Order
    {
        $order = $orderNumber === '' ? null : $this->orderRepository->findOneByOrderNumberIgnoringStoragePage(trim($orderNumber));
        if ($order === null || strcasecmp(trim($email), trim($order->getEmail())) !== 0) {
            throw new InvalidWithdrawalRequestException(
                'Order number and email did not resolve to an order.',
                1784937601
            );
        }
        return $order;
    }

    /**
     * For a logged-in customer, who has identified themselves already and must not have to do so again
     * (Art. 11a(2)): the order is theirs when it was placed under their frontend user.
     *
     * @throws InvalidWithdrawalRequestException
     */
    public function resolveOrderForCustomer(int $orderUid, int $frontendUserUid): Order
    {
        $order = $frontendUserUid > 0 ? $this->orderRepository->findByUidIgnoringStoragePage($orderUid) : null;
        if ($order === null || $order->getFrontendUser() !== $frontendUserUid) {
            throw new InvalidWithdrawalRequestException(
                sprintf('Order %d does not belong to the logged-in customer.', $orderUid),
                1791729349
            );
        }
        return $order;
    }

    /**
     * The orders of a logged-in customer that still have something to withdraw from, newest first as
     * the repository returns them - the list a customer picks from instead of identifying an order.
     *
     * @return Order[]
     */
    public function withdrawableOrdersOf(int $frontendUserUid, WithdrawalSettings $settings, \DateTimeImmutable $at): array
    {
        $orders = [];
        foreach ($this->orderRepository->findByFrontendUser($frontendUserUid) as $order) {
            if ($this->isStillWithdrawable($order, $settings, $at)) {
                $orders[] = $order;
            }
        }
        return $orders;
    }

    /**
     * Every item of the order with what the consumer can still do about it.
     *
     * @return WithdrawalItemState[]
     */
    public function itemStates(Order $order, WithdrawalSettings $settings, \DateTimeImmutable $at): array
    {
        $waiverGiven = $order->getWithdrawalWaiverAcceptedAt() !== null;
        $states = [];
        // A copy: the period calculator walks the same ObjectStorage, and its iterator is not re-entrant.
        foreach ($order->getItems()->toArray() as $item) {
            $deadline = $this->periodCalculator->deadlineFor($order, $item, $settings);
            $states[] = new WithdrawalItemState(
                $item,
                $this->availabilityOf($order, $item, $settings, $at, $waiverGiven),
                $deadline
            );
        }
        return $states;
    }

    public function isStillWithdrawable(Order $order, WithdrawalSettings $settings, \DateTimeImmutable $at): bool
    {
        if ($order->getStatus() === OrderStatus::CANCELLED) {
            return false;
        }
        foreach ($this->itemStates($order, $settings, $at) as $state) {
            if ($state->isSelectable()) {
                return true;
            }
        }
        return false;
    }

    /**
     * @throws WithdrawalEmailMismatchException
     * @throws WithdrawalPeriodExpiredException
     * @throws OrderNotWithdrawableException
     * @throws WithdrawalNameMissingException
     * @throws WithdrawalNoItemSelectedException
     */
    public function withdraw(Order $order, WithdrawalStatement $statement, WithdrawalSettings $settings): void
    {
        $this->assertEmailMatches($order, $statement->email);
        $this->assertNameGiven($statement);
        $selected = $this->selectItems($order, $statement, $settings);
        $submittedAt = \DateTime::createFromImmutable($statement->submittedAt);
        foreach ($selected as $item) {
            $item->setWithdrawnAt($submittedAt);
        }
        $order->setWithdrawnAt($submittedAt);
        $order->setReturnProofAt(null);
        $order->setWithdrawalLog([...$order->getWithdrawalLog(), $this->logEntry($statement, $selected)]);

        $stockItemUids = $this->moveOrder($order, $statement->reason, $selected);
        $this->orderRepository->update($order);
        $this->persistenceManager->persistAll();
        $this->stockService->releaseItems($stockItemUids);

        $this->orderMailService->sendWithdrawalAcknowledgement($order, $statement, $selected);
        $this->orderMailService->sendWithdrawalNotification($order, $statement, $selected);
    }

    private function availabilityOf(Order $order, OrderItem $item, WithdrawalSettings $settings, \DateTimeImmutable $at, bool $waiverGiven): WithdrawalItemAvailability
    {
        if ($item->getWithdrawnAt() !== null) {
            return WithdrawalItemAvailability::WITHDRAWN;
        }
        if ($item->getWithdrawalExclusion()->excludesWithdrawal($waiverGiven)) {
            return WithdrawalItemAvailability::EXEMPT;
        }
        return $this->periodCalculator->isTimely($order, $item, $settings, $at)
            ? WithdrawalItemAvailability::WITHDRAWABLE
            : WithdrawalItemAvailability::EXPIRED;
    }

    /**
     * @return OrderItem[]
     */
    private function selectItems(Order $order, WithdrawalStatement $statement, WithdrawalSettings $settings): array
    {
        if ($order->getStatus() === OrderStatus::CANCELLED) {
            throw new OrderNotWithdrawableException(
                sprintf('Order %d can no longer be withdrawn from status "%s".', $order->getUid() ?? 0, $order->getStatus()->value),
                1752100003
            );
        }
        $states = $this->itemStates($order, $settings, $statement->submittedAt);
        $withdrawable = [];
        foreach ($states as $state) {
            if ($state->isSelectable()) {
                $withdrawable[(int)$state->item->getUid()] = $state->item;
            }
        }
        if ($withdrawable === []) {
            $this->throwNothingWithdrawable($order, $states);
        }
        if ($statement->itemUids === null) {
            return array_values($withdrawable);
        }
        return $this->requestedItems($order, $statement->itemUids, $withdrawable);
    }

    /**
     * @param WithdrawalItemState[] $states
     */
    private function throwNothingWithdrawable(Order $order, array $states): never
    {
        foreach ($states as $state) {
            if ($state->availability === WithdrawalItemAvailability::EXPIRED) {
                throw new WithdrawalPeriodExpiredException(
                    sprintf('Withdrawal period has expired for order %d.', $order->getUid() ?? 0),
                    1752100002
                );
            }
        }
        throw new OrderNotWithdrawableException(
            sprintf('Order %d has no item left that carries a right of withdrawal.', $order->getUid() ?? 0),
            1784937602
        );
    }

    /**
     * @param int[] $itemUids
     * @param array<int, OrderItem> $withdrawable
     * @return OrderItem[]
     */
    private function requestedItems(Order $order, array $itemUids, array $withdrawable): array
    {
        if ($itemUids === []) {
            throw new WithdrawalNoItemSelectedException(
                sprintf('No item of order %d was selected for the withdrawal.', $order->getUid() ?? 0),
                1791729681
            );
        }
        $selected = [];
        foreach (array_unique($itemUids) as $itemUid) {
            if (!isset($withdrawable[$itemUid])) {
                throw new OrderNotWithdrawableException(
                    sprintf('Item %d of order %d cannot be withdrawn.', $itemUid, $order->getUid() ?? 0),
                    1791729351
                );
            }
            $selected[] = $withdrawable[$itemUid];
        }
        return $selected;
    }

    /**
     * @param OrderItem[] $selected
     * @return int[] items that never left the warehouse and have to go back into stock right away
     */
    private function moveOrder(Order $order, string $reason, array $selected): array
    {
        if (in_array($order->getStatus(), self::STATUSES_AFTER_SHIPPING, true)) {
            $this->orderStatusManager->transition($order, OrderStatus::WITHDRAWN, $reason);
            return [];
        }
        if ($this->hasItemLeft($order)) {
            return array_map(static fn(OrderItem $item): int => (int)$item->getUid(), $selected);
        }
        $this->orderStatusManager->transition($order, OrderStatus::CANCELLED, $reason);
        return [];
    }

    private function hasItemLeft(Order $order): bool
    {
        foreach ($order->getItems() as $item) {
            if ($item->getWithdrawnAt() === null) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param OrderItem[] $selected
     * @return array<string, mixed>
     */
    private function logEntry(WithdrawalStatement $statement, array $selected): array
    {
        $items = [];
        foreach ($selected as $item) {
            $items[] = ['uid' => $item->getUid(), 'title' => $item->getTitle(), 'quantity' => $item->getQuantity()];
        }
        return [
            'at' => $statement->submittedAt->format(\DateTimeInterface::ATOM),
            'name' => $statement->name,
            'email' => $statement->email,
            'reason' => $statement->reason,
            'items' => $items,
        ];
    }

    private function assertNameGiven(WithdrawalStatement $statement): void
    {
        if (trim($statement->name) === '') {
            throw new WithdrawalNameMissingException('A withdrawal statement needs the name of the consumer.', 1791729679);
        }
    }

    private function assertEmailMatches(Order $order, string $email): void
    {
        if (strcasecmp(trim($email), trim($order->getEmail())) !== 0) {
            throw new WithdrawalEmailMismatchException(
                sprintf('Submitted email does not match order %d.', $order->getUid() ?? 0),
                1752100001
            );
        }
    }
}
