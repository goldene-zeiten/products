<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Event;

use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;

/**
 * Lets an extension turn the choices stored in a legacy tt_products order line - the product row as it
 * was ordered, with the selected values in its variant columns - into the options of the migrated line.
 */
final class CollectMigratedOrderItemOptionsEvent
{
    /**
     * @var LineItemOption[]
     */
    private array $options = [];

    /**
     * @param array<string, mixed> $legacyRecord the `rec` of the legacy order line
     */
    public function __construct(
        private readonly array $legacyRecord
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getLegacyRecord(): array
    {
        return $this->legacyRecord;
    }

    public function addOption(LineItemOption $option): void
    {
        $this->options[] = $option;
    }

    /**
     * @return LineItemOption[]
     */
    public function getOptions(): array
    {
        return $this->options;
    }
}
