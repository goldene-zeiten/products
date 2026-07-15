..  include:: /Includes.rst.txt
..  _developer-events-export:

======
Export
======

Events fired during order export registry collection.

OrderExportersCollectedEvent
----------------------------

Lets integrators reorder or hide the order exporters the backend is about to offer — hide an ERP
export from certain editors, or push a fulfillment export to the top of the list. Registering an
exporter is done by implementing :php:`OrderExportInterface` (see :ref:`developer-api-order-export`);
this event only post-filters the exporters the registry already collected. Listeners can call
``setExporters()`` to replace the exporter list.

Mutable: Yes (via ``setExporters(array $exporters)``)

Example listener:

..  code-block:: php

    #[AsEventListener]
    final class ReorderExporters
    {
        public function __invoke(OrderExportersCollectedEvent $event): void
        {
            $context = $event->getContext();
            $exporters = $event->getExporters();
            // Filter or reorder exporters by access level, order type, or shop region
            $event->setExporters($filtered);
        }
    }
