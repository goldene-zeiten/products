<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Core\Event\ModifyCategoryQueryEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class ModifyCategoryQueryListener
{
    public static bool $enabled = false;

    public static int $invocationCount = 0;

    public function __invoke(ModifyCategoryQueryEvent $event): void
    {
        self::$invocationCount++;
        if (!self::$enabled) {
            return;
        }
        $query = $event->getQuery();
        $query->matching($query->equals('uid', 1));
    }
}
