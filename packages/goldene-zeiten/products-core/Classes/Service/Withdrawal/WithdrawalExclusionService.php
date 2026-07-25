<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Service\Withdrawal;

use GoldeneZeiten\Products\Core\Domain\Dto\BasketViewModel;
use GoldeneZeiten\Products\Core\Domain\Enum\WithdrawalExclusion;
use GoldeneZeiten\Products\Core\Domain\Model\Order;
use GoldeneZeiten\Products\Core\Domain\Model\OrderItem;

/**
 * Answers which parts of a basket or placed order are exempt from the EU right of withdrawal
 * (Art. 16(c)/(m) Directive 2011/83/EU) - shared by checkout (waiver checkbox), the withdrawal
 * flow (may this order still be withdrawn at all?) and order mails (exemption notices).
 */
final class WithdrawalExclusionService
{
    /**
     * Whether checkout must collect the express consent to immediate supply of digital
     * content, including the acknowledgment of losing the withdrawal right for it.
     */
    public function basketRequiresWaiver(BasketViewModel $basketViewModel): bool
    {
        foreach ($basketViewModel->getItems() as $viewItem) {
            if ($viewItem->getProduct()->getWithdrawalExclusion()->requiresWaiverConsent()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the review step must show the Art. 6(1)(k) notice that personalized items in the
     * basket carry no withdrawal right at all.
     */
    public function basketContainsPersonalized(BasketViewModel $basketViewModel): bool
    {
        foreach ($basketViewModel->getItems() as $viewItem) {
            if ($viewItem->getProduct()->getWithdrawalExclusion() === WithdrawalExclusion::PERSONALIZED) {
                return true;
            }
        }
        return false;
    }

    /**
     * The whole-order withdrawal offered to customers stays available as long as at least one
     * item retains the right - the statutory right on that item cannot be denied because the
     * order also contains exempt items. Only an order consisting entirely of exempt items has
     * nothing left to withdraw.
     */
    public function isFullyExcluded(Order $order): bool
    {
        if (count($order->getItems()) === 0) {
            return false;
        }
        return count($this->excludedItems($order)) === count($order->getItems());
    }

    /**
     * @return OrderItem[]
     */
    public function excludedItems(Order $order): array
    {
        $waiverGiven = $order->getWithdrawalWaiverAcceptedAt() !== null;
        $excluded = [];
        foreach ($order->getItems() as $item) {
            if ($item->getWithdrawalExclusion()->excludesWithdrawal($waiverGiven)) {
                $excluded[] = $item;
            }
        }
        return $excluded;
    }
}
