<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\EventListener;

use GoldeneZeiten\Products\Configuration\GainedVoucherConfigurationFactory;
use GoldeneZeiten\Products\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Service\Voucher\GainedVoucherService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * A voucher-issuing failure must never roll back the order placement.
 */
#[AsEventListener]
final class IssueGainedVoucherListener
{
    public function __construct(
        private readonly GainedVoucherService $gainedVoucherService,
        private readonly GainedVoucherConfigurationFactory $configurationFactory,
        private readonly LoggerInterface $logger
    ) {}

    public function __invoke(AfterOrderPlacedEvent $event): void
    {
        try {
            $configuration = $this->configurationFactory->create($event->getRequest());
            $this->gainedVoucherService->maybeIssue($event->getOrder(), $configuration);
        } catch (\Throwable $exception) {
            $this->logger->error(
                sprintf('Failed to issue a gained voucher for order %d.', $event->getOrder()->getUid() ?? 0),
                ['exception' => $exception]
            );
        }
    }
}
