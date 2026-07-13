:navigation-title: Developer

..  include:: /Includes.rst.txt
..  _developer:

==================================
Developer / Extension Points
==================================

The Products extension exposes integration points through PSR-14 events that fire at critical
stages of the shop lifecycle. Integrators can listen to these events using attribute-based
registration with ``#[AsEventListener]`` to customize behavior — add custom order processing,
filter payment methods, extend exports, or veto orders before creation.

..  contents:: Table of contents
    :local:

Order & Checkout
================

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

Payment
=======

Events fired during payment processing and method collection.

PaymentInitiatedEvent
---------------------

Notifies integrators when payment processing begins — submit payment details to a gateway,
record the transaction, or trigger additional validation. Fired after order creation but
before the customer is redirected to payment or finalization.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class SubmitToPaymentGateway
    {
        public function __invoke(PaymentInitiatedEvent $event): void
        {
            $order = $event->getOrder();
            $paymentResult = $event->getPaymentResult();
            // Submit to payment gateway
        }
    }

PaymentStatusChangedEvent
-------------------------

Notifies integrators when payment status changes — reconcile payment in accounting systems,
update customer notifications, or trigger refund workflows.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class UpdatePaymentReconciliation
    {
        public function __invoke(PaymentStatusChangedEvent $event): void
        {
            $order = $event->getOrder();
            $newStatus = $event->getNewStatus();
            // Update accounting system
        }
    }

PaymentMethodsCollectedEvent
----------------------------

Lets integrators add or filter payment methods shown to customers — inject custom payment
providers, restrict methods by region or cart value, or reorder them. Listeners can call
``setPaymentMethods()`` to replace the available methods.

Mutable: Yes (via ``setPaymentMethods(array $methods)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class RestrictPaymentMethodsByRegion
    {
        public function __invoke(PaymentMethodsCollectedEvent $event): void
        {
            $context = $event->getContext();
            $methods = $event->getPaymentMethods();
            // Filter or reorder methods by region
            $event->setPaymentMethods($filtered);
        }
    }

Invoice
=======

Events fired during invoice number generation and PDF rendering.

InvoiceNumberGeneratedEvent
---------------------------

Notifies integrators when an invoice number has been assigned — log it, sync it with an
accounting system, or publish it to external services. This fires when invoice payment method
is initiated.

Mutable: No

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class LogInvoiceNumber
    {
        public function __invoke(InvoiceNumberGeneratedEvent $event): void
        {
            $order = $event->getOrder();
            $invoiceNumber = $event->getInvoiceNumber();
            // Log to accounting system
        }
    }

BeforeInvoiceRenderedEvent
--------------------------

Lets integrators customize the invoice PDF before rendering — add company letterhead,
custom stamps, or replace it entirely with a custom implementation. Listeners can call
``setReplacementPdf()`` to replace the entire document.

Mutable: Yes (via ``setReplacementPdf(string $pdf)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class CustomizeInvoicePdf
    {
        public function __invoke(BeforeInvoiceRenderedEvent $event): void
        {
            $order = $event->getOrder();
            $html = $event->getHtml();
            // Customize PDF or replace entirely
            $event->setReplacementPdf($customPdf);
        }
    }

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

Export
======

Events fired during order export registry collection.

OrderExportersCollectedEvent
----------------------------

Lets integrators add or filter order exporters — inject custom exporters for SAP, analytics
platforms, or fulfillment partners. Listeners can call ``setExporters()`` to replace the
exporter list.

Mutable: Yes (via ``setExporters(array $exporters)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class AddCustomExporters
    {
        public function __invoke(OrderExportersCollectedEvent $event): void
        {
            $exporters = $event->getExporters();
            // Add or filter custom exporters
            $event->setExporters($modified);
        }
    }
