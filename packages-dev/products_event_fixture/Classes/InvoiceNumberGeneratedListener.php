<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventFixture;

use GoldeneZeiten\Products\Core\Event\InvoiceNumberGeneratedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class InvoiceNumberGeneratedListener
{
    /**
     * @var string[]
     */
    public static array $invoiceNumbers = [];

    public function __invoke(InvoiceNumberGeneratedEvent $event): void
    {
        self::$invoiceNumbers[] = $event->getInvoiceNumber();
    }
}
