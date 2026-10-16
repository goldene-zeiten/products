<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Personalization\EventListener;

use GoldeneZeiten\Products\Core\Event\CollectMigratedLegacyColumnsEvent;
use GoldeneZeiten\Products\Personalization\Updates\LegacyOptionColumns;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * The tt_products columns configured as option columns are migrated by this extension, not lost.
 */
#[AsEventListener('products-personalization/report-option-columns-as-migrated')]
final class ReportOptionColumnsAsMigrated
{
    public function __construct(
        private readonly LegacyOptionColumns $optionColumns
    ) {}

    public function __invoke(CollectMigratedLegacyColumnsEvent $event): void
    {
        if ($event->getLegacyTable() !== 'tt_products') {
            return;
        }
        foreach (array_keys($this->optionColumns->getSelectionColumns()) as $column) {
            $event->addColumn($column);
        }
    }
}
