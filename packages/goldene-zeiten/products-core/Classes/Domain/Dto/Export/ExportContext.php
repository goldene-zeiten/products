<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\Dto\Export;

use GoldeneZeiten\Products\Core\Domain\Model\Order;
use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * Immutable input an order exporter is offered and executed with. Carries everything an exporter may
 * base its availability decision on, so implementations never have to read the request or the session
 * themselves.
 */
#[Exclude]
final readonly class ExportContext
{
    public function __construct(
        private Order $order,
        private int $backendUserUid = 0
    ) {}

    public function getOrder(): Order
    {
        return $this->order;
    }

    public function getBackendUserUid(): int
    {
        return $this->backendUserUid;
    }
}
