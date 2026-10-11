<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Voucher\EventListener;

use GoldeneZeiten\Products\Core\Event\AfterOrderPlacedEvent;
use GoldeneZeiten\Products\Core\Service\Database\IsolatedWrite;
use GoldeneZeiten\Products\Voucher\Configuration\GainedVoucherConfigurationFactory;
use GoldeneZeiten\Products\Voucher\Service\GainedVoucherService;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;

/**
 * A voucher-issuing failure must never roll back the order placement. The listener runs inside the
 * placement's transaction, so the issuing gets a savepoint of its own.
 */
#[AsEventListener]
final class IssueGainedVoucherListener
{
    public function __construct(
        private readonly GainedVoucherService $gainedVoucherService,
        private readonly GainedVoucherConfigurationFactory $configurationFactory,
        private readonly LoggerInterface $logger,
        private readonly IsolatedWrite $isolatedWrite
    ) {}

    public function __invoke(AfterOrderPlacedEvent $event): void
    {
        $failure = $this->isolatedWrite->run(
            'tx_products_domain_model_voucher',
            fn() => $this->gainedVoucherService->maybeIssue($event->getOrder(), $this->configurationFactory->create($event->getRequest()))
        );
        if ($failure !== null) {
            $this->logger->error(
                sprintf('Failed to issue a gained voucher for order %d.', $event->getOrder()->getUid() ?? 0),
                ['exception' => $failure]
            );
        }
    }
}
