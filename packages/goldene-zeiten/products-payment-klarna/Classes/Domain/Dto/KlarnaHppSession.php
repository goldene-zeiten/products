<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Payment\Klarna\Domain\Dto;

use Symfony\Component\DependencyInjection\Attribute\Exclude;

/**
 * A Klarna Hosted Payment Page session: its id and the customer-facing URL the shopper is redirected to
 * in order to pay on Klarna's hosted pages.
 */
#[Exclude]
final readonly class KlarnaHppSession
{
    public function __construct(
        public string $hppSessionId,
        public string $redirectUrl,
    ) {}
}
