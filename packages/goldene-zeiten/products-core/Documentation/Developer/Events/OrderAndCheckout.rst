..  include:: /Includes.rst.txt
..  _developer-events-order-and-checkout:

=================
Order & Checkout
=================

Events fired during the checkout flow and order creation.

AfterOrderPlacedEvent
---------------------

Notifies integrators when an order is placed and persisted — send a confirmation email,
create a shipping label, or trigger a fulfillment request. The order is ready for processing
and the basket has been cleared.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class SendOrderConfirmationEmail
    {
        public function __invoke(AfterOrderPlacedEvent $event): void
        {
            $order = $event->getOrder();
            // Send confirmation email with $order details
        }
    }

BeforeOrderPlacedEvent
----------------------

Lets integrators veto suspicious orders before they are created — enforce minimum order value,
reject orders from specific regions, or flag for manual approval. Listeners can call
``veto()`` with a reason to prevent order creation and rollback the transaction.

Mutable: Yes (via ``veto(string $reason)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class EnforceMinimumOrderValue
    {
        public function __invoke(BeforeOrderPlacedEvent $event): void
        {
            $basket = $event->getBasketViewModel();
            if ($basket->getTotalGross()->getCents() < 5000) { // €50
                $event->veto('Minimum order value is €50.');
            }
        }
    }

BeforeOrderFinalizedEvent
--------------------------

Notifies integrators just before an order transitions to finalized — a last chance to verify
inventory, apply additional discounts, or reject the order. The order is not yet persisted
when this fires, but payment has been initiated.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class ApplyFinalDiscounts
    {
        public function __invoke(BeforeOrderFinalizedEvent $event): void
        {
            $order = $event->getOrder();
            $paymentResult = $event->getPaymentResult();
            // Apply final validation or discounts
        }
    }

AfterOrderFinalizedEvent
------------------------

Notifies integrators once an order is fully finalized — push it into an ERP, trigger
fulfilment, or notify a warehouse. The order is already persisted, so this is a read-only
notification.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class PushOrderToERP
    {
        public function __invoke(AfterOrderFinalizedEvent $event): void
        {
            $order = $event->getOrder();
            // Push order to ERP system
        }
    }

OrderStatusChangedEvent
-----------------------

Notifies integrators when an order transitions to a new status — send status update emails,
update fulfillment systems, or trigger shipment workflows. Fired whenever an order's status
changes.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class SendOrderStatusNotification
    {
        public function __invoke(OrderStatusChangedEvent $event): void
        {
            $order = $event->getOrder();
            $newStatus = $event->getNewStatus();
            // Send status update notification
        }
    }

LowStockThresholdReachedEvent
-----------------------------

Notifies integrators when stock falls below the configured threshold — send an alert to
the warehouse, trigger an automatic reorder, or block further sales of the item.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class AlertWarehouseOnLowStock
    {
        public function __invoke(LowStockThresholdReachedEvent $event): void
        {
            $productUid = $event->getProductUid();
            $newStock = $event->getNewStock();
            // Send alert to warehouse
        }
    }

MapBillingToDeliveryAddressEvent
--------------------------------

Lets integrators derive or adjust the delivery address from the billing address while the order
is built. Useful for copying billing into delivery when the customer gave none, or normalising
it for a carrier.

Mutable: Yes (via {@see MapBillingToDeliveryAddressEvent::setDeliveryAddress()})

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class CopyBillingToDelivery
    {
        public function __invoke(MapBillingToDeliveryAddressEvent $event): void
        {
            if ($event->getDeliveryAddress() === null) {
                $event->setDeliveryAddress($event->getBillingAddress());
            }
        }
    }

ModifyOrderTrackingEvent
------------------------

Lets shipping/fulfilment extensions attach tracking links to an order as its detail page renders.
A pluggable collection so several extensions can each contribute links — a parcel-tracking URL
per carrier, a returns portal, a delivery-status page.

Mutable: Yes (pluggable via {@see ModifyOrderTrackingEvent::addTrackingLink()})

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class AttachCarrierTracking
    {
        public function __invoke(ModifyOrderTrackingEvent $event): void
        {
            $order = $event->getOrder();
            if ($trackingUrl = $this->lookupTracking($order)) {
                $event->addTrackingLink(
                    new OrderTrackingLink('Track your parcel', $trackingUrl)
                );
            }
        }
    }
