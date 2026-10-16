<?php

declare(strict_types=1);

namespace GoldeneZeiten\Products\Core\Updates;

/**
 * How a tt_products installation used the `price2` column, as declared in the extension configuration
 * `migration.legacyPrice2`.
 */
enum LegacyPrice2Usage: string
{
    case Undecided = 'undecided';
    case Unused = 'unused';
    case Selling = 'selling';
    case Reseller = 'reseller';
}
