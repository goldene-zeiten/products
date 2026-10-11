<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Express;

use GoldeneZeiten\Products\Core\Domain\Dto\Payment\PaymentResult;
use GoldeneZeiten\Products\Core\Domain\Model\Order;

/**
 * One wallet payment, bound to the buyer's authorization, that {@see ExpressOrderService::place()} settles
 * once the order exists and its total is known. A provider hands one to the order service per confirm.
 *
 * The order service hands the settlement the order to pay and never trusts the answer: the settled amount
 * the result reports must equal the order total, otherwise it calls {@see refund()}.
 */
interface ExpressSettlementInterface
{
    /**
     * Charges exactly the order's gross total. Implementations throw their own
     * {@see Exception\ExpressOrderExceptionInterface} exception when the wallet declines, and report the
     * amount the gateway really moved in {@see PaymentResult::getSettledAmount()}.
     */
    public function settle(Order $order): PaymentResult;

    /**
     * Gives back a payment that was settled for an order that could not be placed.
     */
    public function refund(PaymentResult $settled): void;
}
