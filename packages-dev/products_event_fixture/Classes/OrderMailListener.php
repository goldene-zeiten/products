<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Core\Event\BeforeOrderMailSentEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class OrderMailListener
{
    /**
     * @var string[]
     */
    public static array $templates = [];
    public static bool $enabled = false;

    public function __invoke(BeforeOrderMailSentEvent $event): void
    {
        if (!self::$enabled) {
            return;
        }
        self::$templates[] = $event->getTemplate();
        $event->getEmail()->addCc('archive@example.com');
    }
}
