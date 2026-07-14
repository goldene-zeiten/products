..  include:: /Includes.rst.txt
..  _developer-api-order-export:

====================
Order Export API
====================

The Order Export API is a provider contract that enables integrators to implement order export
formats tailored to ERP systems, fulfillment partners, analytics platforms, or accounting
software. Because order export is inherently shop-specific, **no concrete exporter ships with
this extension** — integrators register their own implementations.

**Location:** :php:`GoldeneZeiten\Products\Export\OrderExportInterface`

Lifecycle: Discovery & Execution
=================================

The order export API operates in two distinct phases:

**Discovery Phase**

When the backend is about to display the export action for an order, it calls
:php:`OrderExportRegistry::getAvailable(ExportContext)`. The registry:

1. Filters all registered exporters by calling :php:`isAvailable(ExportContext)` on each.
2. Sorts the available exporters by :php:`getPriority()` (highest first).
3. Dispatches :php:`OrderExportersCollectedEvent` to allow listeners to reorder or filter the list.
4. Returns the final array of available exporters to the backend.

The editor sees only the exporters that passed the availability check for this order.

**Execution Phase**

The editor selects an exporter by its identifier. The registry resolves the exporter by identifier
via :php:`OrderExportRegistry::get(string)` and calls its :php:`export(Order)` method to produce
the payload.

Interface Contract
==================

..  code-block:: php

    interface OrderExportInterface
    {
        public function getIdentifier(): string;
        public function getLabel(): string;
        public function getContentType(): string;
        public function getFileExtension(): string;
        public function isAvailable(ExportContext $context): bool;
        public function getPriority(): int;
        public function export(Order $order): string;
    }

**Methods:**

:php:`getIdentifier(): string`
    A unique identifier for this exporter (e.g., :code:`'erp_sap'`, :code:`'fulfillment_amazon'`).
    Used by the backend to resolve which exporter was selected.

:php:`getLabel(): string`
    Human-readable label shown in the backend UI (e.g., :code:`'SAP ERP Export'`).

:php:`getContentType(): string`
    MIME type of the exported payload (e.g., :code:`'text/csv'`, :code:`'application/json'`).

:php:`getFileExtension(): string`
    File extension for the exported file (e.g., :code:`'csv'`, :code:`'json'`).

:php:`isAvailable(ExportContext $context): bool`
    **Discovery phase:** Return :code:`true` if this exporter may be offered for the given order
    and backend user. An exporter that only applies to paid orders, or only to certain editors,
    returns :code:`false` and is not offered. Called once per exporter during discovery.

:php:`getPriority(): int`
    Higher values are offered first. Exporters with the same priority keep their registration
    order. Use :code:`0` for default priority. Example: :code:`100` for a primary exporter,
    :code:`10` for secondary.

:php:`export(Order $order): string`
    **Execution phase:** Produce and return the export payload as a string. The backend writes
    this string to a file with the extension from :php:`getFileExtension()` and the MIME type
    from :php:`getContentType()`.

Registration
============

The interface carries the :php:`#[AutoconfigureTag('products.order_export')]` attribute, so any
class implementing :php:`OrderExportInterface` is automatically registered — no manual entry in
:file:`Configuration/Services.yaml` is required, as long as your extension's :file:`Services.yaml`
has :code:`autoconfigure: true` (the Symfony/TYPO3 default).

ExportContext
==============

The :php:`ExportContext` is an immutable, read-only value object passed to both
:php:`isAvailable()` and dispatched with the event. It carries:

:php:`getOrder(): Order`
    The order being exported.

:php:`getBackendUserUid(): int`
    The UID of the backend user who initiated the export. Use this to enforce
    per-editor export restrictions, not the request or session.

**Important:** Exporters must never read the HTTP request or session directly. All contextual
information they may decide on is available via :php:`ExportContext`.

OrderExportRegistry
====================

The registry is the service point for both phases:

:php:`getAvailable(ExportContext $context): array<OrderExportInterface>`
    Returns the list of available (filtered and sorted) exporters for the given context.
    Dispatches :php:`OrderExportersCollectedEvent` at the end of collection.

:php:`get(string $identifier): OrderExportInterface`
    Resolves an exporter by identifier. Throws :php:`OrderExporterNotFoundException` (code
    1783900000) if the identifier is unknown — useful for catching editor errors or stale
    selections.

Filtering After Discovery: OrderExportersCollectedEvent
========================================================

After the registry collects and sorts the available exporters, it dispatches
:php:`OrderExportersCollectedEvent`. **This event is NOT how you register an exporter** —
registration happens by implementing the interface.

The event exists to let listeners **reorder or filter** the already-collected list. Common use
cases:

-   Hide an ERP export from non-admin editors.
-   Promote a fulfillment export to the top for specific order types.
-   Remove an exporter that failed a pre-flight check (e.g., API quota exhausted).

The event is **mutable** via :php:`setExporters()`. See the example below.

Example: CSV Exporter for Paid Orders
======================================

This example exports paid orders to CSV, with products and totals:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\Export;

    use GoldeneZeiten\Products\Domain\Dto\Export\ExportContext;
    use GoldeneZeiten\Products\Domain\Enum\PaymentStatus;
    use GoldeneZeiten\Products\Domain\Model\Order;
    use GoldeneZeiten\Products\Export\OrderExportInterface;

    /**
     * Export paid orders to CSV for fulfillment partners.
     */
    final class CsvOrderExporter implements OrderExportInterface
    {
        public function getIdentifier(): string
        {
            return 'csv_fulfillment';
        }

        public function getLabel(): string
        {
            return 'CSV for Fulfillment';
        }

        public function getContentType(): string
        {
            return 'text/csv';
        }

        public function getFileExtension(): string
        {
            return 'csv';
        }

        public function isAvailable(ExportContext $context): bool
        {
            $order = $context->getOrder();

            // Only export paid orders
            return $order->getPaymentStatus() === PaymentStatus::PAID;
        }

        public function getPriority(): int
        {
            return 10; // Offered above default exporters
        }

        public function export(Order $order): string
        {
            $lines = [];

            // Header
            $lines[] = implode(',', [
                'OrderNumber',
                'Email',
                'BillingCity',
                'ProductName',
                'Quantity',
                'UnitPrice',
                'TotalGross',
            ]);

            // Order header
            $billingCity = $order->getBillingAddress()?->getCity() ?? '';

            // Products
            foreach ($order->getItems() as $item) {
                $lines[] = implode(',', [
                    $order->getOrderNumber(),
                    $order->getEmail(),
                    $billingCity,
                    $item->getProduct()->getName(),
                    (string)$item->getQuantity(),
                    (string)$item->getPricePerUnit()->getAmount(),
                    (string)$item->getTotalPrice()->getAmount(),
                ]);
            }

            return implode("\n", $lines);
        }
    }

Configuration
==============

In your extension's :file:`Configuration/Services.yaml`, enable autowiring and autoconfiguration
(both are defaults in TYPO3/Symfony):

..  code-block:: yaml

    services:
      _defaults:
        autowire: true
        autoconfigure: true

      MyVendor\MyExtension\Export\:
        resource: '../Classes/Export/*'

Your exporter class will be automatically discovered and registered.

Listening to OrderExportersCollectedEvent
==========================================

To reorder or filter exporters after discovery (e.g., hide an exporter from certain editors),
attach a listener:

..  code-block:: php

    <?php

    declare(strict_types=1);

    namespace MyVendor\MyExtension\EventListener;

    use GoldeneZeiten\Products\Event\OrderExportersCollectedEvent;
    use Symfony\Component\EventDispatcher\Attribute\AsEventListener;

    #[AsEventListener]
    final class FilterExportByRole
    {
        public function __invoke(OrderExportersCollectedEvent $event): void
        {
            $backendUserUid = $event->getContext()->getBackendUserUid();

            // Example: only admins (uid=1) can export to ERP
            if ($backendUserUid !== 1) {
                $filtered = array_filter(
                    $event->getExporters(),
                    fn($exporter) => $exporter->getIdentifier() !== 'erp_sap'
                );
                $event->setExporters(array_values($filtered));
            }
        }
    }

Why This API Is an Interface, Not an Event
============================================

-   **Registry question:** "Which exporters are available for this order?" Events cannot answer
    this — a listener is optional and cannot decide what to offer.
-   **Resolution by identifier:** The backend selected exporter ID :code:`'erp_sap'`. The registry
    must resolve it to the actual exporter. Events cannot do this; only a service registry can.
-   **Fail-closed guarantees:** A forgotten event listener silently does nothing. A forgotten
    implementation is obvious in the codebase and fails immediately.
-   **Immutability of contract:** Each exporter defines its own availability, priority, and
    payload. These cannot change based on listener order; the interface makes the contract visible.

The event (:php:`OrderExportersCollectedEvent`) exists to allow last-minute filtering and
reordering — for cases where the availability decision is made at discovery time, not at
implementation time.
