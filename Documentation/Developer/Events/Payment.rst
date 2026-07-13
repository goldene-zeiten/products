..  include:: /Includes.rst.txt
..  _developer-events-payment:

=======
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
