<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventListener;

use GoldeneZeiten\Products\Event\AfterOrderFinalizedEvent;
use GoldeneZeiten\Products\Service\MailService;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use TYPO3\CMS\Core\Attribute\AsEventListener;

#[AsEventListener]
final class SendOrderEmailsListener implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    public function __construct(
        private readonly MailService $mailService
    ) {}

    public function __invoke(AfterOrderFinalizedEvent $event): void
    {
        try {
            $this->mailService->sendOrderConfirmation($event->getOrder());
        } catch (\Throwable $exception) {
            $this->logger?->error(
                sprintf('Failed to send order confirmation mail for order %d.', $event->getOrder()->getUid() ?? 0),
                ['exception' => $exception]
            );
        }
    }
}
