<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Shipping\DhlExpress\Rating;

use GoldeneZeiten\Products\Core\Domain\Dto\Shipping\ShippingContext;
use GoldeneZeiten\Products\Shipping\DhlExpress\Configuration\DhlExpressConfiguration;
use GoldeneZeiten\Products\Shipping\DhlExpress\Domain\Dto\DhlExpressRate;

/**
 * Fetches live DHL Express rates for a basket. Split behind an interface so the shipping provider can be
 * tested against a fake without HTTP.
 */
interface DhlExpressRatingClient
{
    /**
     * @return DhlExpressRate[]
     */
    public function rate(ShippingContext $context, DhlExpressConfiguration $configuration): array;
}
