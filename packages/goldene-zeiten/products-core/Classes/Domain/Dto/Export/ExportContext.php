<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Export;

use GoldeneZeiten\Products\Core\Domain\Dto\Order\OrderData;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Immutable input an order exporter is offered and executed with. Carries everything an exporter may
 * base its availability decision on, so implementations never have to read the request or the session
 * themselves. The order is an {@see OrderData} snapshot.
 */
#[Exclude]
final readonly class ExportContext
{
    public function __construct(
        private OrderData $order,
        private int $backendUserUid = 0
    ) {}

    public function getOrder(): OrderData
    {
        return $this->order;
    }

    public function getBackendUserUid(): int
    {
        return $this->backendUserUid;
    }
}
