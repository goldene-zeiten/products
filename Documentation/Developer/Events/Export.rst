..  include:: /Includes.rst.txt
..  _developer-events-export:

======
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
