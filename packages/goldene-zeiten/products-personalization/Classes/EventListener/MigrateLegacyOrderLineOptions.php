<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\EventListener;

use GoldeneZeiten\Products\Core\Domain\Dto\LineItemOption;
use GoldeneZeiten\Products\Core\Domain\ValueObject\Money;
use GoldeneZeiten\Products\Core\Event\CollectMigratedOrderItemOptionsEvent;
use GoldeneZeiten\Products\Personalization\Updates\LegacyOptionColumns;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * A legacy order line holds the values the customer chose in the option columns of its product row; they
 * become the options of the migrated line, personalizing as `migration.personalizingOptionColumns` says.
 */
#[AsEventListener('products-personalization/migrate-legacy-order-line-options')]
final class MigrateLegacyOrderLineOptions
{
    public function __construct(
        private readonly LegacyOptionColumns $optionColumns
    ) {}

    public function __invoke(CollectMigratedOrderItemOptionsEvent $event): void
    {
        $record = $event->getLegacyRecord();
        foreach ($this->optionColumns->getSelectionColumns() as $column => $title) {
            $value = trim((string)($record[$column] ?? ''));
            if ($value === '') {
                continue;
            }
            $event->addOption(new LineItemOption(
                $column,
                $title,
                $value,
                $value,
                Money::fromCents(0),
                $this->optionColumns->isPersonalizingChoice($column, $value)
            ));
        }
    }
}
