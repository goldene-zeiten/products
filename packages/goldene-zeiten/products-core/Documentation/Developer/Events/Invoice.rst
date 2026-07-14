..  include:: /Includes.rst.txt
..  _developer-events-invoice:

=======
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
