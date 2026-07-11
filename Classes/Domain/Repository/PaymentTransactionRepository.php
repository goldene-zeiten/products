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
     * A transaction already marked COMPLETED represents a finished payment attempt and is never
     * reused; only a still-open one (pending/redirect_required/failed) gets updated in place on
     * re-initiation instead of inserting a duplicate row, matching legacy's "update unless already
     * approved" behaviour.
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
     * Blessed lookup for a RedirectPaymentMethodInterface implementation's own
     * handleReturn()/handleWebhook() idempotency check: "does a transaction for this gateway's own
     * reference already exist" - call this before mutating anything so a replayed
     * return/webhook call is a no-op rather than double-processing the same payment.
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
