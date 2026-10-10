<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\PaymentFixture;

use GoldeneZeiten\Products\Core\Event\AfterOrderFinalizedEvent;
use GoldeneZeiten\Products\Core\Event\PaymentStatusChangedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Records which orders were accepted and which payment status changes were announced, so a test can prove
 * a replayed or concurrent callback announces neither a second time.
 */
final class PaymentLifecycleEventRecorder
{
    /**
     * @var list<int>
     */
    public static array $finalizedOrderUids = [];

    /**
     * @var list<string>
     */
    public static array $paymentStatusChanges = [];

    #[AsEventListener('products-payment-fixture/record-finalized')]
    public function recordFinalized(AfterOrderFinalizedEvent $event): void
    {
        self::$finalizedOrderUids[] = (int)$event->getOrder()->getUid();
    }

    #[AsEventListener('products-payment-fixture/record-payment-status')]
    public function recordPaymentStatusChange(PaymentStatusChangedEvent $event): void
    {
        self::$paymentStatusChanges[] = sprintf(
            '%d:%s->%s',
            $event->getOrderUid(),
            $event->getPreviousStatus()->value,
            $event->getNewStatus()->value
        );
    }

    public static function reset(): void
    {
        self::$finalizedOrderUids = [];
        self::$paymentStatusChanges = [];
    }
}
