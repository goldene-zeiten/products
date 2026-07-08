<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventListener;

use GoldeneZeiten\Products\Event\LowStockThresholdReachedEvent;
use GoldeneZeiten\Products\Service\OrderMailService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class LowStockWarningListener
{
    public function __construct(
        private readonly OrderMailService $mailService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(LowStockThresholdReachedEvent $event): void
    {
        try {
            $this->mailService->sendLowStockWarning($event->getTitle(), $event->getNewStock());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to send low-stock warning mail for "%s".', $event->getTitle()),
                ['exception' => $exception]
            );
        }
    }
}
