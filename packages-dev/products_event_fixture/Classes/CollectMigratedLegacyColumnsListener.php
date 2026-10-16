<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Core\Event\CollectMigratedLegacyColumnsEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class CollectMigratedLegacyColumnsListener
{
    /**
     * @var string[]
     */
    public static array $columns = [];

    public function __invoke(CollectMigratedLegacyColumnsEvent $event): void
    {
        if ($event->getLegacyTable() !== 'tt_products') {
            return;
        }
        foreach (self::$columns as $column) {
            $event->addColumn($column);
        }
    }
}
