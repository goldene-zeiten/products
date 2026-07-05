<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventListener;

use GoldeneZeiten\Products\Event\AfterOrderFinalizedEvent;
use GoldeneZeiten\Products\Service\OrderMailService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class SendOrderEmailsListener
{
    public function __construct(
        private readonly OrderMailService $mailService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(AfterOrderFinalizedEvent $event): void
    {
        $order = $event->getOrder();

        try {
            $this->mailService->sendOrderConfirmation($order);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send order confirmation mail for order %d.', $order->getUid() ?? 0),
                ['exception' => $exception]
            );
        }

        try {
            $this->mailService->sendMerchantNotification($order);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send merchant notification mail for order %d.', $order->getUid() ?? 0),
                ['exception' => $exception]
            );
        }
    }
}
