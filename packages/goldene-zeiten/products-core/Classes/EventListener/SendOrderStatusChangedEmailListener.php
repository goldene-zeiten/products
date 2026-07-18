<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\EventListener;

use GoldeneZeiten\Products\Core\Domain\Repository\OrderRepository;
use GoldeneZeiten\Products\Core\Event\OrderStatusChangedEvent;
use GoldeneZeiten\Products\Core\Service\OrderMailService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Sends the order status-changed email for an {@see OrderStatusChangedEvent}: loads the order for the
 * event's uid and hands it to {@see OrderMailService}.
 */
#[AsEventListener]
final class SendOrderStatusChangedEmailListener
{
    public function __construct(
        private readonly OrderMailService $mailService,
        private readonly OrderRepository $orderRepository,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(OrderStatusChangedEvent $event): void
    {
        $order = $this->orderRepository->findByUidIgnoringStoragePage($event->getOrderUid());
        if ($order === null) {
            return;
        }
        try {
            $this->mailService->sendOrderStatusChanged($order, $event->getPreviousStatus(), $event->getNewStatus());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send order status changed mail for order %d.', $event->getOrderUid()),
                ['exception' => $exception]
            );
        }
    }
}
