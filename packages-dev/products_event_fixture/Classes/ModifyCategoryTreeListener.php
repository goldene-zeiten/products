<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Core\Event\ModifyCategoryTreeEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class ModifyCategoryTreeListener
{
    public static bool $enabled = false;

    public static int $invocationCount = 0;

    public function __invoke(ModifyCategoryTreeEvent $event): void
    {
        self::$invocationCount++;
        if (!self::$enabled) {
            return;
        }
        $tree = $event->getTree();
        array_shift($tree);
        $event->setTree(array_values($tree));
    }
}
