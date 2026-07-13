<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Event\ModifyProductListEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class ModifyProductListListener
{
    public static bool $enabled = false;

    public static int $invocationCount = 0;

    public function __invoke(ModifyProductListEvent $event): void
    {
        self::$invocationCount++;
        if (!self::$enabled) {
            return;
        }
        $event->setProducts([]);
    }
}
