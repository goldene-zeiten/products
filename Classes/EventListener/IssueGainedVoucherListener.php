<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventListener;

use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Service\Voucher\GainedVoucherService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * Issuing a reward voucher is a bonus on top of a successful order, not a condition for one - a
 * failure here (e.g. code-generation exhaustion) must never roll back the placement, same
 * reasoning as SendOrderEmailsListener never failing the request over a mail error.
 */
#[AsEventListener]
final class IssueGainedVoucherListener
{
    public function __construct(
        private readonly GainedVoucherService $gainedVoucherService,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(AfterOrderPlacedEvent $event): void
    {
        try {
            $this->gainedVoucherService->maybeIssue($event->getOrder());
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to issue a gained voucher for order %d.', $event->getOrder()->getUid() ?? 0),
                ['exception' => $exception]
            );
        }
    }
}
