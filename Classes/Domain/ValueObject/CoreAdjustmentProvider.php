<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Domain\ValueObject;

/**
 * The provider identifiers the extension itself puts on the checkout adjustments it produces. Naming them
 * in one place lets a provider find another's adjustment - a free-shipping discount has to recognise the
 * carrier's cost to offset it - without either side hard-coding the same string twice.
 */
final class CoreAdjustmentProvider
{
    /**
     * What the carrier charges. A free-shipping discount offsets this.
     */
    public const SHIPPING = 'core.shipping';

    /**
     * What the shop adds on top of the carrier - the bulky-goods surcharge. Not offset by a free-shipping
     * discount: handling an oversized item costs the shop the same whoever pays for the transport.
     */
    public const SHIPPING_SURCHARGE = 'core.shipping.surcharge';

    public const HANDLING = 'core.handling';

    public const DEPOSIT = 'core.deposit';

    public const VOUCHER = 'core.voucher';

    public const VOUCHER_FREE_SHIPPING = 'core.voucher.free_shipping';

    public const CREDIT_POINTS = 'core.credit_points';
}
