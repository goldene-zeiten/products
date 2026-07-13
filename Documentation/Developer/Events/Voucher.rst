..  include:: /Includes.rst.txt
..  _developer-events-voucher:

=======
Voucher
=======

Events fired during voucher generation and redemption.

VoucherGeneratedEvent
---------------------

Notifies integrators when a reward voucher is auto-generated for a customer — log the
voucher code, notify the customer about their reward, or sync it to a loyalty system.
Fired after an order is placed if it qualifies for automatic voucher generation.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class NotifyCustomerOfRewardVoucher
    {
        public function __invoke(VoucherGeneratedEvent $event): void
        {
            $voucher = $event->getVoucher();
            $order = $event->getOrder();
            // Notify customer about earned voucher
        }
    }

VoucherRedeemedEvent
--------------------

Notifies integrators when a voucher is redeemed as part of an order — track loyalty
redemption, update the customer's reward balance, or sync the transaction to backend systems.
Fired during order creation after all applicable vouchers are locked and recorded.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class TrackLoyaltyRedemption
    {
        public function __invoke(VoucherRedeemedEvent $event): void
        {
            $voucher = $event->getVoucher();
            $order = $event->getOrder();
            $discount = $event->getDiscountAmount();
            // Track redemption in loyalty system
        }
    }
