<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use GoldeneZeiten\Products\Core\Backend\Exception\OrderStatusWriteFailedException;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use GoldeneZeiten\Products\Core\Domain\Enum\PaymentStatus;
use GoldeneZeiten\Products\Core\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Core\Event\PaymentStatusChangedEvent;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InvalidOrderStatusTransitionException;
use GoldeneZeiten\Products\Core\Service\Order\Exception\InvalidPaymentStatusTransitionException;
use Psr\EventDispatcher\EventDispatcherInterface;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\DataHandling\DataHandler;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Changes an order's status or payment status from the backend order module: validates the transition
 * against the order's current state, writes the new value (appending the status log for status changes),
 * and dispatches the matching status-changed event.
 */
final class OrderStatusWriter
{
    private const TABLE = 'tx_products_domain_model_order';

    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {}

    public function changeStatus(int $orderUid, OrderStatus $target, ?string $note, BackendUserAuthentication $beUser): void
    {
        $row = $this->fetchRow($orderUid, ['status', 'status_log']);
        if ($row === null) {
            return;
        }
        $current = OrderStatus::from((string)$row['status']);
        if ($current === $target) {
            return;
        }
        if (!$current->canTransitionTo($target)) {
            throw new InvalidOrderStatusTransitionException(
                sprintf('Order status cannot transition from "%s" to "%s".', $current->value, $target->value),
                1752710401
            );
        }

        $log = $this->appendStatusLog((string)$row['status_log'], $current, $target, $note);
        $this->writeDatamap($orderUid, [
            'status' => $target->value,
            'status_log' => (string)json_encode($log),
        ], $beUser);
        $this->eventDispatcher->dispatch(new OrderStatusChangedEvent($orderUid, $current, $target));
    }

    public function changePaymentStatus(int $orderUid, PaymentStatus $target, BackendUserAuthentication $beUser): void
    {
        $row = $this->fetchRow($orderUid, ['payment_status']);
        if ($row === null) {
            return;
        }
        $current = PaymentStatus::from((string)$row['payment_status']);
        if ($current === $target) {
            return;
        }
        if (!$current->canTransitionTo($target)) {
            throw new InvalidPaymentStatusTransitionException(
                sprintf('Payment status cannot transition from "%s" to "%s".', $current->value, $target->value),
                1752710402
            );
        }

        $this->writeDatamap($orderUid, ['payment_status' => $target->value], $beUser);
        $this->eventDispatcher->dispatch(new PaymentStatusChangedEvent($orderUid, $current, $target));
    }

    /**
     * @param string[] $columns
     * @return array<string, mixed>|null
     */
    private function fetchRow(int $orderUid, array $columns): ?array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder->select(...$columns)
            ->from(self::TABLE)
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        return $row === false ? null : $row;
    }

    /**
     * Appends a log entry in the exact shape {@see \GoldeneZeiten\Products\Core\Service\Order\OrderStatusManager}
     * writes, so the frontend and backend paths produce identical status_log JSON.
     *
     * @return array<int, array<string, string>>
     */
    private function appendStatusLog(string $currentLogJson, OrderStatus $from, OrderStatus $to, ?string $note): array
    {
        $decoded = json_decode($currentLogJson, true);
        $log = is_array($decoded) ? array_values($decoded) : [];
        $entry = [
            'from' => $from->value,
            'to' => $to->value,
            'at' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
        ];
        if ($note !== null && $note !== '') {
            $entry['note'] = $note;
        }
        $log[] = $entry;
        return $log;
    }

    /**
     * @param array<string, string> $fields
     */
    private function writeDatamap(int $orderUid, array $fields, BackendUserAuthentication $beUser): void
    {
        $dataHandler = GeneralUtility::makeInstance(DataHandler::class);
        $dataHandler->start([self::TABLE => [$orderUid => $fields]], []);
        $dataHandler->BE_USER = $beUser;
        $dataHandler->process_datamap();
        if ($dataHandler->errorLog !== []) {
            throw new OrderStatusWriteFailedException(implode(' ', $dataHandler->errorLog), 1752710400);
        }
    }
}
