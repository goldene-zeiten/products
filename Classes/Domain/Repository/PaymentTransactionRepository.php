<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Domain\Repository;

use GoldeneZeiten\Products\Domain\Enum\PaymentResultState;
use GoldeneZeiten\Products\Domain\Model\PaymentTransaction;
use TYPO3\CMS\Extbase\Persistence\Repository;

/**
 * @extends Repository<PaymentTransaction>
 */
final class PaymentTransactionRepository extends Repository
{
    /**
     * Excludes COMPLETED transactions, which are never reused/updated in place.
     */
    public function findOneNotYetApproved(int $orderUid, string $paymentMethod): ?PaymentTransaction
    {
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $result = $query->matching($query->logicalAnd(
            $query->equals('orderUid', $orderUid),
            $query->equals('paymentMethod', $paymentMethod),
            $query->logicalNot($query->equals('state', PaymentResultState::COMPLETED->value))
        ))->setLimit(1)->execute()->getFirst();
        return $result instanceof PaymentTransaction ? $result : null;
    }

    /**
     * Idempotency check for handleReturn()/handleWebhook(): a replayed call becomes a no-op.
     */
    public function findOneByExternalId(string $paymentMethod, string $externalId): ?PaymentTransaction
    {
        if ($externalId === '') {
            return null;
        }
        $query = $this->createQuery();
        $query->getQuerySettings()->setRespectStoragePage(false);
        $result = $query->matching($query->logicalAnd(
            $query->equals('paymentMethod', $paymentMethod),
            $query->equals('externalId', $externalId)
        ))->setLimit(1)->execute()->getFirst();
        return $result instanceof PaymentTransaction ? $result : null;
    }
}
