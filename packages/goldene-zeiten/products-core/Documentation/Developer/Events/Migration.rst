..  include:: /Includes.rst.txt
..  _developer-events-migration:

=========
Migration
=========

Events fired by the tt_products upgrade wizards, for extensions migrating further legacy data.

CollectMigratedLegacyColumnsEvent
---------------------------------

The product and article wizards report every column of ``tt_products`` and ``tt_products_articles``
that carries data they do not migrate. An extension migrating such columns itself names them here, so
the report only lists what is really left behind.

Mutable: Yes (via ``addColumn(string $column)``)

..  code-block:: php

    #[AsEventListener]
    final class ReportEngravingColumnAsMigrated
    {
        public function __invoke(CollectMigratedLegacyColumnsEvent $event): void
        {
            if ($event->getLegacyTable() === 'tt_products') {
                $event->addColumn('size');
            }
        }
    }

CollectMigratedOrderItemOptionsEvent
------------------------------------

A legacy order line taken from ``orderData`` carries the product row as it was ordered, with the values
the customer chose in its variant columns (``color = '52'``). An extension turns them into the options of
the migrated order line; a personalizing option marks the line as exempt from withdrawal.

Mutable: Yes (via ``addOption(LineItemOption $option)``)

..  code-block:: php

    #[AsEventListener]
    final class MigrateRingSize
    {
        public function __invoke(CollectMigratedOrderItemOptionsEvent $event): void
        {
            $size = trim((string)($event->getLegacyRecord()['color'] ?? ''));
            if ($size !== '') {
                $event->addOption(new LineItemOption('color', 'Ring size', $size, $size, Money::fromCents(0)));
            }
        }
    }
