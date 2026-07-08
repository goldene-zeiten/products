<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventListener;

use GoldeneZeiten\Products\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Service\OrderMailService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class SendOrderStatusChangedEmailListener
{
    public function __construct(
        private readonly OrderMailService $mailService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(OrderStatusChangedEvent $event): void
    {
        try {
            $this->mailService->sendOrderStatusChanged($event->getOrder(), $event->getPreviousStatus(), $event->getNewStatus());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send order status changed mail for order %d.', $event->getOrder()->getUid() ?? 0),
                ['exception' => $exception]
            );
        }
    }
}
