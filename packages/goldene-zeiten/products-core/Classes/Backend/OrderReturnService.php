<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Backend;

use GoldeneZeiten\Products\Core\Backend\Exception\OrderAccessDeniedException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderNotAwaitingReturnException;
use GoldeneZeiten\Products\Core\Backend\Exception\OrderNotShippedException;
use GoldeneZeiten\Products\Core\Domain\Enum\OrderStatus;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The backend steps of the right of withdrawal after shipping: record the delivery that starts the
 * withdrawal period, record the proof that withdrawn goods are on their way back (which releases the
 * refund), and confirm the receipt of the returned goods (which puts them back into stock).
 */
final class OrderReturnService
{
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly OrderAccessGuard $accessGuard,
        private readonly OrderStatusWriter $orderStatusWriter,
    ) {}

    /**
     * @throws OrderAccessDeniedException
     * @throws OrderNotShippedException
     */
    public function markDelivered(int $orderUid, BackendUserAuthentication $backendUser): void
    {
        $row = $this->fetchWritableRow($orderUid, $backendUser);
        if (!in_array(OrderStatus::tryFrom((string)$row['status']), [OrderStatus::SHIPPED, OrderStatus::COMPLETED], true)) {
            throw new OrderNotShippedException(
                sprintf('Order %d has not been shipped, so it cannot be marked delivered.', $orderUid),
                1791730325
            );
        }
        $this->orderStatusWriter->writeFields($orderUid, ['delivered_at' => (string)time()], $backendUser);
    }

    /**
     * @throws OrderAccessDeniedException
     * @throws OrderNotAwaitingReturnException
     */
    public function recordReturnProof(int $orderUid, BackendUserAuthentication $backendUser): void
    {
        $this->assertAwaitingReturn($orderUid, $backendUser);
        $this->orderStatusWriter->writeFields($orderUid, ['return_proof_at' => (string)time()], $backendUser);
    }

    /**
     * The order is completed when items remain with the customer, cancelled when everything came back.
     * Putting the withdrawn items back into stock follows from the status change.
     *
     * @throws OrderAccessDeniedException
     * @throws OrderNotAwaitingReturnException
     */
    public function markReturned(int $orderUid, BackendUserAuthentication $backendUser): void
    {
        $this->assertAwaitingReturn($orderUid, $backendUser);
        $target = $this->hasItemLeft($orderUid) ? OrderStatus::COMPLETED : OrderStatus::CANCELLED;
        $this->orderStatusWriter->writeFields($orderUid, ['returned_at' => (string)time()], $backendUser);
        $this->orderStatusWriter->changeStatus($orderUid, $target, null, $backendUser);
    }

    private function assertAwaitingReturn(int $orderUid, BackendUserAuthentication $backendUser): void
    {
        $row = $this->fetchWritableRow($orderUid, $backendUser);
        if (OrderStatus::tryFrom((string)$row['status']) !== OrderStatus::WITHDRAWN) {
            throw new OrderNotAwaitingReturnException(
                sprintf('Order %d is not waiting for withdrawn goods to come back.', $orderUid),
                1791730327
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchWritableRow(int $orderUid, BackendUserAuthentication $backendUser): array
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_order');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        $row = $queryBuilder->select('pid', 'status')
            ->from('tx_products_domain_model_order')
            ->where($queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)))
            ->executeQuery()
            ->fetchAssociative();
        if ($row === false || !$this->accessGuard->mayWrite((int)$row['pid'], $backendUser)) {
            throw new OrderAccessDeniedException(
                sprintf('The current backend user may not change order %d.', $orderUid),
                1791730322
            );
        }
        return $row;
    }

    private function hasItemLeft(int $orderUid): bool
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('tx_products_domain_model_orderitem');
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        return (int)$queryBuilder->count('uid')
            ->from('tx_products_domain_model_orderitem')
            ->where(
                $queryBuilder->expr()->eq('parent_order', $queryBuilder->createNamedParameter($orderUid, Connection::PARAM_INT)),
                $queryBuilder->expr()->eq('withdrawn_at', $queryBuilder->createNamedParameter(0, Connection::PARAM_INT))
            )
            ->executeQuery()
            ->fetchOne() > 0;
    }
}
