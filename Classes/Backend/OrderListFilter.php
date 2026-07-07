<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Backend;

/**
 * Filter criteria for `OrderManagementRepository::fetchFiltered()`.
 */
final readonly class OrderListFilter
{
    public function __construct(
        public ?string $status = null,
        public ?string $orderNumber = null,
        public ?string $email = null,
        public ?\DateTimeImmutable $dateFrom = null,
        public ?\DateTimeImmutable $dateTo = null,
    ) {}
}
