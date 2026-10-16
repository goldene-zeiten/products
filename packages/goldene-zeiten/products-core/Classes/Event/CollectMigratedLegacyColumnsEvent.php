<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

/**
 * Lets an extension that migrates further columns of a legacy table (tt_products, tt_products_articles)
 * name them, so the upgrade wizards do not report their data as lost.
 */
final class CollectMigratedLegacyColumnsEvent
{
    /**
     * @var string[]
     */
    private array $columns = [];

    public function __construct(
        private readonly string $legacyTable
    ) {}

    public function getLegacyTable(): string
    {
        return $this->legacyTable;
    }

    public function addColumn(string $column): void
    {
        $this->columns[] = $column;
    }

    /**
     * @return string[]
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
}
